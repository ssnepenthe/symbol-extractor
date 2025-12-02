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

/*
 * This file was initially based on a version from the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 */

namespace SymbolMapGenerator;

use Composer\Pcre\Preg;
use Symfony\Component\Finder\Finder;

/**
 * SymbolMapGenerator
 *
 * @author Gyula Sallai <salla016@gmail.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class SymbolMapGenerator
{
    /**
     * @var list<string>
     */
    private $extensions;

    /**
     * @var FileList|null
     */
    private $scannedFiles = null;

    /**
     * @var SymbolMap
     */
    private $classMap;

    /**
     * @var SymbolMap
     */
    private $functionMap;

    /**
     * @var non-empty-string
     */
    private $streamWrappersRegex;

    /**
     * @param list<string> $extensions File extensions to scan for symbols in the given paths
     */
    public function __construct(array $extensions = ['php', 'inc'])
    {
        $this->extensions = $extensions;
        $this->classMap = new SymbolMap;
        $this->functionMap = new SymbolMap;
        $this->streamWrappersRegex = sprintf('{^(?:%s)://}', implode('|', array_map('preg_quote', stream_get_wrappers())));
    }

    /**
     * Iterate over all files in the given directory searching for classes
     *
     * @param string|\Traversable<\SplFileInfo>|array<\SplFileInfo> $path The path to search in or an array/traversable of SplFileInfo (e.g. symfony/finder instance)
     * @return array<class-string, non-empty-string> A class map array
     *
     * @throws \RuntimeException When the path is neither an existing file nor directory
     *
     * @todo Only keeping this temporarily to minimize updates needed for compat tests.
     */
    public static function createMap($path): array
    {
        $generator = new self();

        $generator->scanPaths($path);

        return $generator->getClassMap()->getMap();
    }

    /**
     * When calling scanPaths repeatedly with paths that may overlap, calling this will ensure that the same symbol is never scanned twice
     *
     * You can provide your own FileList instance or use the default one if you pass no argument
     *
     * @return $this
     */
    public function avoidDuplicateScans(?FileList $scannedFiles = null): self
    {
        $this->scannedFiles = $scannedFiles ?? new FileList;

        return $this;
    }

    public function getClassMap(): SymbolMap
    {
        return $this->classMap;
    }

    public function getFunctionMap(): SymbolMap
    {
        return $this->functionMap;
    }

    /**
     * Iterate over all files in the given directory searching for symbols
     *
     * @param string|\Traversable<\SplFileInfo>|array<\SplFileInfo> $path         The path to search in or an array/traversable of SplFileInfo (e.g. symfony/finder instance)
     * @param non-empty-string|null                                 $excluded     Regex that matches file paths to be excluded from the symbol map
     * @param array<string>                                         $excludedDirs Optional dirs to exclude from search relative to $path
     *
     * @throws \RuntimeException When the path is neither an existing file nor directory
     */
    public function scanPaths($path, ?string $excluded = null, array $excludedDirs = []): void
    {
        if (is_string($path)) {
            if (is_file($path)) {
                $path = [new \SplFileInfo($path)];
            } elseif (is_dir($path) || strpos($path, '*') !== false) {
                $path = Finder::create()
                    ->files()
                    ->followLinks()
                    ->name('/\.(?:'.implode('|', array_map('preg_quote', $this->extensions)).')$/')
                    ->in($path)
                    ->exclude($excludedDirs);
            } else {
                throw new \RuntimeException(
                    'Could not scan for symbols inside "'.$path.'" which does not appear to be a file nor a folder'
                );
            }
        }

        $cwd = realpath(self::getCwd());

        foreach ($path as $file) {
            $filePath = $file->getPathname();
            if (!in_array(pathinfo($filePath, PATHINFO_EXTENSION), $this->extensions, true)) {
                continue;
            }

            $isStreamWrapperPath = Preg::isMatch($this->streamWrappersRegex, $filePath);
            if (!self::isAbsolutePath($filePath) && !$isStreamWrapperPath) {
                $filePath = $cwd . '/' . $filePath;
                $filePath = self::normalizePath($filePath);
            } else {
                $filePath = Preg::replace('{(?<!:)[\\\\/]{2,}}', '/', $filePath);
            }

            if ('' === $filePath) {
                throw new \LogicException('Got an empty $filePath for '.$file->getPathname());
            }

            $realPath = $isStreamWrapperPath
                ? $filePath
                : realpath($filePath);

            // fallback just in case but this really should not happen
            if (false === $realPath) {
                throw new \RuntimeException('realpath of '.$filePath.' failed to resolve, got false');
            }

            // if a list of scanned files is given, avoid scanning twice the same file to save cycles and avoid generating warnings
            // in case a PSR-0/4 declaration follows another more specific one, or a classmap declaration, which covered this file already
            if ($this->scannedFiles !== null && $this->scannedFiles->contains($realPath)) {
                continue;
            }

            // check the realpath of the file against the excluded paths as the path might be a symlink and the excluded path is realpath'd so symlink are resolved
            if (null !== $excluded && Preg::isMatch($excluded, strtr($realPath, '\\', '/'))) {
                continue;
            }
            // check non-realpath of file for directories symlink in project dir
            if (null !== $excluded && Preg::isMatch($excluded, strtr($filePath, '\\', '/'))) {
                continue;
            }

            $symbols = PhpFileParser::findSymbols($filePath);
            if ($this->scannedFiles !== null) {
                // classmap autoload rules always collect all classes so for these we definitely do not want to scan again
                $this->scannedFiles->add($realPath);
            }

            foreach ($symbols['classLike'] as $symbol) {
                if (!$this->classMap->hasSymbol($symbol)) {
                    $this->classMap->addSymbol($symbol, $filePath);
                } elseif ($filePath !== $this->classMap->getSymbolPath($symbol)) {
                    $this->classMap->addAmbiguousSymbol($symbol, $filePath);
                }
            }

            foreach ($symbols['function'] as $symbol) {
                if (!$this->functionMap->hasSymbol($symbol)) {
                    $this->functionMap->addSymbol($symbol, $filePath);
                } elseif ($filePath !== $this->functionMap->getSymbolPath($symbol)) {
                    $this->functionMap->addAmbiguousSymbol($symbol, $filePath);
                }
            }
        }
    }

    /**
     * Checks if the given path is absolute
     *
     * @see Composer\Util\Filesystem::isAbsolutePath
     *
     * @param  string $path
     * @return bool
     */
    private static function isAbsolutePath(string $path): bool
    {
        return strpos($path, '/') === 0 || substr($path, 1, 1) === ':' || strpos($path, '\\\\') === 0;
    }

    /**
     * Normalize a path. This replaces backslashes with slashes, removes ending
     * slash and collapses redundant separators and up-level references.
     *
     * @see Composer\Util\Filesystem::normalizePath
     *
     * @param  string $path Path to the file or directory
     * @return string
     */
    private static function normalizePath(string $path): string
    {
        $parts = [];
        $path = strtr($path, '\\', '/');
        $prefix = '';
        $absolute = '';

        // extract windows UNC paths e.g. \\foo\bar
        if (strpos($path, '//') === 0 && \strlen($path) > 2) {
            $absolute = '//';
            $path = substr($path, 2);
        }

        // extract a prefix being a protocol://, protocol:, protocol://drive: or simply drive:
        if (Preg::isMatchStrictGroups('{^( [0-9a-z]{2,}+: (?: // (?: [a-z]: )? )? | [a-z]: )}ix', $path, $match)) {
            $prefix = $match[1];
            $path = substr($path, \strlen($prefix));
        }

        if (strpos($path, '/') === 0) {
            $absolute = '/';
            $path = substr($path, 1);
        }

        $up = false;
        foreach (explode('/', $path) as $chunk) {
            if ('..' === $chunk && (\strlen($absolute) > 0 || $up)) {
                array_pop($parts);
                $up = !(\count($parts) === 0 || '..' === end($parts));
            } elseif ('.' !== $chunk && '' !== $chunk) {
                $parts[] = $chunk;
                $up = '..' !== $chunk;
            }
        }

        // ensure c: is normalized to C:
        $prefix = Preg::replaceCallback('{(?:^|://)[a-z]:$}i', function (array $m) { return strtoupper((string) $m[0]); }, $prefix);

        return $prefix.$absolute.implode('/', $parts);
    }

    /**
     * @see Composer\Util\Platform::getCwd
     */
    private static function getCwd(): string
    {
        $cwd = getcwd();

        if (false === $cwd) {
            throw new \RuntimeException('Could not determine the current working directory');
        }

        return $cwd;
    }
}
