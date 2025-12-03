<?php declare(strict_types=1);

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymbolMapGenerator;

use Composer\Pcre\Preg;
use RuntimeException;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @internal
 */
class PhpFileCleaner
{
    /** @var array<array{name: string, length: int, pattern: non-empty-string}> */
    private static $typeConfig;

    /** @var non-empty-string */
    private static $restPattern;

    /**
     * @readonly
     * @var string
     */
    private $contents;

    /**
     * @readonly
     * @var int
     */
    private $len;

    /**
     * @readonly
     * @var int
     */
    private $maxMatches;

    /** @var int */
    private $index = 0;

    /**
     * @param string[] $types
     */
    public static function setTypeConfig(array $types): void
    {
        foreach ($types as $type) {
            $type = \strtolower($type);

            self::$typeConfig[$type[0]] = [
                'name' => $type,
                'length' => \strlen($type),
                'pattern' => '{.\b(?<![\$:>])'.$type.'\s++[a-zA-Z_\x7f-\xff:][a-zA-Z0-9_\x7f-\xff:\-]*+}Ais',
            ];
        }

        // @todo original was not case-insensitive... I wonder if that was by-design or if it just wasn't necessary so wasn't even a consideration.
        self::$restPattern = '{[^?"\'</'.implode('', array_keys(self::$typeConfig)).']+}Ai';
    }

    public function __construct(string $contents, int $maxMatches)
    {
        $this->contents = $contents;
        $this->len = \strlen($this->contents);
        $this->maxMatches = $maxMatches;
    }

    public function clean(): string
    {
        $clean = '';

        while ($this->index < $this->len) {
            // @todo if file ends with closing php tag followed by newline this adds in a php open tag.
            $this->skipToPhp();
            $clean .= '<?';

            while ($this->index < $this->len) {
                $char = $this->contents[$this->index];
                if ($char === '?' && $this->peek('>')) {
                    $clean .= '?>';
                    $this->index += 2;
                    continue 2;
                }

                if ($this->skipAllStrings()) {
                    $clean .= 'null';
                    continue;
                }

                if ($this->skipAllComments()) {
                    continue;
                }

                $lowerChar = \strtolower($char);

                if ($this->maxMatches === 1 && isset(self::$typeConfig[$lowerChar])) {
                    $type = self::$typeConfig[$lowerChar];
                    if (
                        \strtolower(\substr($this->contents, $this->index, $type['length'])) === $type['name']
                        && Preg::isMatch($type['pattern'], $this->contents, $match, 0, $this->index - 1)
                    ) {
                        return $clean . $match[0];
                    }
                }

                if (isset(self::$typeConfig[$lowerChar])) {
                    $type = self::$typeConfig[$lowerChar];

                    if (
                        \strtolower(\substr($this->contents, $this->index, $type['length'])) === $type['name']
                        && Preg::isMatch($type['pattern'], $this->contents, $match, 0, $this->index - 1)
                    ) {
                        $clean .= $this->skipTo('{');
                        $this->skipBracketedBody();
                        $clean .= '{}';
                        continue;
                    }
                }

                $this->index += 1;
                if ($this->match(self::$restPattern, $match)) {
                    $clean .= $char . $match[0];
                    $this->index += \strlen($match[0]);
                } else {
                    $clean .= $char;
                }
            }
        }

        return $clean;
    }

    private function skipToPhp(): void
    {
        while ($this->index < $this->len) {
            if ($this->contents[$this->index] === '<' && $this->peek('?')) {
                $this->index += 2;
                break;
            }

            $this->index += 1;
        }
    }

    private function skipString(string $delimiter): void
    {
        $this->index += 1;
        while ($this->index < $this->len) {
            if ($this->contents[$this->index] === '\\' && ($this->peek('\\') || $this->peek($delimiter))) {
                $this->index += 2;
                continue;
            }

            if ($this->contents[$this->index] === $delimiter) {
                $this->index += 1;
                break;
            }

            $this->index += 1;
        }
    }

    private function skipComment(): void
    {
        $this->index += 2;
        while ($this->index < $this->len) {
            if ($this->contents[$this->index] === '*' && $this->peek('/')) {
                $this->index += 2;
                break;
            }

            $this->index += 1;
        }
    }

    private function skipToNewline(): void
    {
        while ($this->index < $this->len) {
            if ($this->contents[$this->index] === "\r" || $this->contents[$this->index] === "\n") {
                return;
            }

            $this->index += 1;
        }
    }

    private function skipHeredoc(string $delimiter): void
    {
        $firstDelimiterChar = $delimiter[0];
        $delimiterLength = \strlen($delimiter);
        $delimiterPattern = '{'.preg_quote($delimiter).'(?![a-zA-Z0-9_\x80-\xff])}A';

        while ($this->index < $this->len) {
            // check if we find the delimiter after some spaces/tabs
            switch ($this->contents[$this->index]) {
                case "\t":
                case " ":
                    $this->index += 1;
                    continue 2;
                case $firstDelimiterChar:
                    if (
                        \substr($this->contents, $this->index, $delimiterLength) === $delimiter
                        && $this->match($delimiterPattern)
                    ) {
                        $this->index += $delimiterLength;

                        return;
                    }

                    break;
            }

            // skip the rest of the line
            while ($this->index < $this->len) {
                $this->skipToNewline();

                // skip newlines
                while ($this->index < $this->len && ($this->contents[$this->index] === "\r" || $this->contents[$this->index] === "\n")) {
                    $this->index += 1;
                }

                break;
            }
        }
    }

    private function skipTo(string $character): string
    {
        $return = '';

        while ($this->index < $this->len) {
            if ($this->skipAllStrings()) {
                $return .= 'null';
                continue;
            }

            if ($this->skipAllComments()) {
                continue;
            }

            if ($this->contents[$this->index] === $character) {
                break;
            }

            $return .= $this->contents[$this->index];
            $this->index += 1;
        }

        if ($return === '') {
            // Shouldn't happen since we are only using to find opening curly bracket after symbol has been identified.
            // @todo We probably shouldn't throw anyway...
            throw new RuntimeException("Character not found: {$character}");
        }

        return $return;
    }

    private function skipAllStrings(): bool
    {
        $char = $this->contents[$this->index];

        if ($char === '"') {
            $this->skipString('"');

            return true;
        }

        if ($char === "'") {
            $this->skipString("'");

            return true;
        }

        if ($char === "<" && $this->peek('<') && $this->match('{<<<[ \t]*+([\'"]?)([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*+)\\1(?:\r\n|\n|\r)}A', $match)) {
            $this->index += \strlen($match[0]);
            $this->skipHeredoc($match[2]);

            return true;
        }

        return false;
    }

    private function skipAllComments(): bool
    {
        // @todo it's not clear why we are removing comments since this is only used internally with the result of a
        // call to php_strip_whitespace which should have already removed all comments...
        if ($this->contents[$this->index] === '/') {
            if ($this->peek('/')) {
                $this->skipToNewline();

                return true;
            }

            if ($this->peek('*')) {
                $this->skipComment();

                return true;
            }
        }

        return false;
    }

    private function skipBracketedBody(): void
    {
        $this->index += 1;
        $depth = 1;
        while ($this->index < $this->len) {
            if ($this->skipAllStrings() || $this->skipAllComments()) {
                continue;
            }

            $char = $this->contents[$this->index];

            if ($char === '{') {
                $depth += 1;
                $this->index += 1;
                continue;
            }

            if ($char === '}') {
                $depth -= 1;
                $this->index += 1;

                if ($depth === 0) {
                    break;
                }

                continue;
            }

            $this->index += 1;
        }

        if ($this->index === $this->len && $depth !== 0) {
            // Shouldn't happen if we are working with valid PHP.
            // @todo We probably shouldn't throw anyway...
            throw new RuntimeException('Failed to find end of bracketed body.');
        }
    }

    private function peek(string $char): bool
    {
        return $this->index + 1 < $this->len && $this->contents[$this->index + 1] === $char;
    }

    /**
     * @param non-empty-string $regex
     * @param null|array<mixed> $match
     * @param-out array<int|string, string> $match
     */
    private function match(string $regex, ?array &$match = null): bool
    {
        return Preg::isMatchStrictGroups($regex, $this->contents, $match, 0, $this->index);
    }
}
