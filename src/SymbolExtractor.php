<?php

namespace SymbolMapGenerator;

use PhpToken;

final class SymbolExtractor
{
    /**
     * @var PhpToken[]
     */
    private array $tokens;
    private int $count;
    private int $pos = 0;

    public function __construct(string $file)
    {
        $code = file_get_contents($file);

        $this->tokens = PhpToken::tokenize($code);
        $this->count = count($this->tokens);
    }

    public function extract()
    {
        $declarations = new SymbolSet();
        $namespace = '';

        while ($this->pos < $this->count) {
            if (! $this->advanceToFirst(T_CLASS, T_ENUM, T_FUNCTION, T_INTERFACE, T_NAMESPACE, T_TRAIT)) {
                break;
            }

            if ($this->currentTokenIs(T_NAMESPACE)) {
                $namespace = '';

                while ($this->pos < $this->count) {
                    if (! $this->advanceToFirst(T_NAME_QUALIFIED, T_NS_SEPARATOR, T_STRING, ';', '{')) {
                        // @todo something wrong - wat do?
                        break 2;
                    }

                    if ($this->currentTokenIs(';', '{')) {
                        continue 2;
                    }

                    $namespace .= $this->tokens[$this->pos]->text;

                    $this->pos += 1;
                }
            } else {
                if ($this->currentTokenIs(T_CLASS)) {
                    $prev = $this->getPreviousSignificantToken();

                    // Anonymous class.
                    if ($prev?->is(T_NEW)) {
                        $this->skipDeclarationBody();

                        continue;
                    }

                    // ::class magic constant.
                    if ($prev?->is(T_DOUBLE_COLON)) {
                        $this->pos += 1;

                        continue;
                    }
                }

                if ($this->currentTokenIs(T_FUNCTION)) {
                    // Anonymous function.
                    if ($this->getNextSignificantToken()?->is('(')) {
                        $this->skipDeclarationBody();

                        continue;
                    }
                }

                $type = strtolower($this->tokens[$this->pos]->text);

                if (! $this->advanceToFirst(T_STRING, '{', '(')) {
                    // @todo something wrong - wat do?
                    break;
                }

                if (! $this->currentTokenIs(T_STRING)) {
                    // @todo something wrong - wat do?
                    break;
                }

                $name = $this->tokens[$this->pos]->text;

                $adder = 'add' . ucfirst($type);
                $declarations->{$adder}($namespace === '' ? $name : ($namespace . '\\' . $name));

                $this->skipDeclarationBody();
            }
        }

        return $declarations->getAll();
    }

    private function advanceToFirst(...$kinds): bool
    {
        while ($this->pos < $this->count) {
            foreach ($kinds as $kind) {
                if ($this->tokens[$this->pos]->is($kind)) {
                    return true;
                }
            }

            $this->pos += 1;
        }

        return false;
    }

    private function currentTokenIs(...$kinds): bool
    {
        foreach ($kinds as $kind) {
            if ($this->tokens[$this->pos]->is($kind)) {
                return true;
            }
        }

        return false;
    }

    private function getNextSignificantToken(): ?PhpToken
    {
        $pos = $this->pos + 1;

        while ($pos < $this->count) {
            if ($this->tokens[$pos]->isIgnorable()) {
                $pos += 1;
                continue;
            }

            return $this->tokens[$pos];
        }

        return null;
    }

    private function getPreviousSignificantToken(): ?PhpToken
    {
        $pos = $this->pos - 1;

        while ($pos >= 0) {
            if ($this->tokens[$pos]->isIgnorable()) {
                $pos -= 1;
                continue;
            }

            return $this->tokens[$pos];
        }

        return null;
    }

    private function skipDeclarationBody()
    {
        if (! $this->advanceToFirst('{')) {
            // @todo something wrong - wat do?
            return;
        }

        $this->pos += 1;
        $depth = 1;

        while ($this->pos < $this->count) {
            if ($this->tokens[$this->pos]->is('{')) {
                $depth += 1;
            } elseif ($this->tokens[$this->pos]->is('}')) {
                $depth -= 1;

                if ($depth === 0) {
                    $this->pos += 1;

                    return;
                }
            }

            $this->pos += 1;
        }

        if ($this->pos === $this->count && $depth !== 0) {
            // @todo something wrong - wat do?
        }
    }
}