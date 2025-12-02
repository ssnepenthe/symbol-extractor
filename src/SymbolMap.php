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

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class SymbolMap implements \Countable
{
    /**
     * @var array<string, non-empty-string>
     */
    public $map = [];

    /**
     * @var array<string, array<non-empty-string>>
     */
    private $ambiguousSymbols = [];

    /**
     * Returns the symbol map, which is a list of paths indexed by symbol name
     *
     * @return array<string, non-empty-string>
     */
    public function getMap(): array
    {
        return $this->map;
    }

    /**
     * A map of symbol names to their list of ambiguous paths
     *
     * This occurs when the same symbol can be found in several files
     *
     * To get the path the symbol is being mapped to, call getSymbolPath
     *
     * By default, paths that contain test(s), fixture(s), example(s) or stub(s) are ignored
     * as those are typically not problematic when they're dummy symbols in the tests folder.
     * If you want to get these back as well you can pass false to $duplicatesFilter. Or
     * you can pass your own pattern to exclude if you need to change the default.
     *
     * @param non-empty-string|false $duplicatesFilter
     *
     * @return array<string, array<non-empty-string>>
     */
    public function getAmbiguousSymbols($duplicatesFilter = '{/(test|fixture|example|stub)s?/}i'): array
    {
        if (false === $duplicatesFilter) {
            return $this->ambiguousSymbols;
        }

        if (true === $duplicatesFilter) {
            throw new \InvalidArgumentException('$duplicatesFilter should be false or a string with a valid regex, got true.');
        }

        $ambiguousSymbols = [];
        foreach ($this->ambiguousSymbols as $symbol => $paths) {
            $paths = array_filter($paths, function ($path) use ($duplicatesFilter): bool {
                return !Preg::isMatch($duplicatesFilter, strtr($path, '\\', '/'));
            });
            if (\count($paths) > 0) {
                $ambiguousSymbols[$symbol] = array_values($paths);
            }
        }

        return $ambiguousSymbols;
    }

    /**
     * Sorts the symbol map alphabetically by symbol names
     */
    public function sort(): void
    {
        ksort($this->map);
    }

    /**
     * @param string $symbolName
     * @param non-empty-string $path
     */
    public function addSymbol(string $symbolName, string $path): void
    {
        $this->map[$symbolName] = $path;
    }

    /**
     * @param string $symbolName
     * @return non-empty-string
     */
    public function getSymbolPath(string $symbolName): string
    {
        if (!isset($this->map[$symbolName])) {
            throw new \OutOfBoundsException('Symbol '.$symbolName.' is not present in the map');
        }

        return $this->map[$symbolName];
    }

    /**
     * @param string $symbolName
     */
    public function hasSymbol(string $symbolName): bool
    {
        return isset($this->map[$symbolName]);
    }

    /**
     * @param string $symbolName
     * @param non-empty-string $path
     */
    public function addAmbiguousSymbol(string $symbolName, string $path): void
    {
        $this->ambiguousSymbols[$symbolName][] = $path;
    }

    public function count(): int
    {
        return \count($this->map);
    }
}
