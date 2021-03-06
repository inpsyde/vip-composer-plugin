<?php

/*
 * This file is part of the vip-composer-plugin package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Inpsyde\VipComposer\Git;

use Composer\Util\Filesystem;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\Utils\Unzipper;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class MirrorCopier
{
    private const EXCLUDED_FILES = [
        '.composer_compiled_assets',
        '.eslintrc',
        '.phpstorm.meta.php',
        '.phpunit.result.cache',
        'bitbucket-pipelines.yml',
        'changelog.md',
        'changelog.txt',
        'gulpfile.js',
        'gruntfile.js',
        'node_modules',
        'npm-shrinkwrap.json',
        'package.json',
        'package-lock.json',
        'phpcs.xml',
        'phpcs.xml.dist',
        'phpunit.xml',
        'phpunit.xml.dist',
        'readme.md',
        'readme.txt',
        'studio.json',
        'tsconfig.json',
        'webpack.config.js',
    ];

    private const EXCLUDED_EXT = [
        'coffee',
        'error',
        'jsx',
        'less',
        'lock',
        'log',
        'phar',
        'sass',
        'scss',
        'temp',
        'tmp',
        'ts',
        'tsx',
    ];

    /**
     * @var Io
     */
    private $io;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var Unzipper
     */
    private $unzipper;

    /**
     * @param string $path
     * @return bool
     */
    public static function accept(string $path): bool
    {
        if (is_dir($path)) {
            return static::acceptPath($path);
        }

        if (!is_file($path)) {
            return false;
        }

        $basename = basename($path);
        $ext = pathinfo($path, PATHINFO_EXTENSION);

        return
            !in_array(strtolower($basename), self::EXCLUDED_FILES, true)
            && !in_array(strtolower($ext), self::EXCLUDED_EXT, true)
            && static::acceptPath($path);
    }

    /**
     * @param string $path
     * @return bool
     */
    private static function acceptPath(string $path): bool
    {
        $path = str_replace('\\', '/', $path);
        if (is_file($path) && basename($path) === '.gitkeep') {
            return static::acceptPath(dirname($path));
        }

        return
            strpos($path, 'node_modules/') === false
            && (strpos($path, '/.git') === false);
    }

    /**
     * @param Io $io
     * @param Filesystem $filesystem
     * @param Unzipper $unzipper
     */
    public function __construct(Io $io, Filesystem $filesystem, Unzipper $unzipper)
    {
        $this->filesystem = $filesystem;
        $this->io = $io;
        $this->unzipper = $unzipper;
    }

    /**
     * @param string $source
     * @param string $target
     * @return bool
     */
    public function copy(string $source, string $target): bool
    {
        $source = $this->filesystem->normalizePath($source);
        $target = $this->filesystem->normalizePath($target);
        if (!self::accept($source)) {
            return false;
        }
        if (!is_dir($source)) {
            return copy($source, $target);
        }

        $this->filesystem->ensureDirectoryExists($target);

        /** @var \RecursiveDirectoryIterator $iterator */
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $this->filesystem->ensureDirectoryExists($target);

        $links = [];

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $filepath = $this->filesystem->normalizePath((string)$file);
            if (!self::accept($filepath)) {
                continue;
            }
            $targetPath = "{$target}/" . $iterator->getSubPathname();

            if ($this->isInLinks($filepath, $links)) {
                continue;
            }

            if ($file->isDir()) {
                $this->isLinkedGit($filepath)
                    ? $links[$filepath] = $targetPath
                    : $this->filesystem->ensureDirectoryExists($targetPath);
                continue;
            }

            $result = copy($file->getPathname(), $targetPath);
            if (!$result) {
                return false;
            }
        }

        return $links ? $this->copyLinks($links) : true;
    }

    /**
     * @param string $filepath
     * @return bool
     */
    private function isLinkedGit(string $filepath): bool
    {
        if (!is_dir($filepath)) {
            return false;
        }

        if (
            !$this->filesystem->isSymlinkedDirectory($filepath)
            && !$this->filesystem->isJunction($filepath)
        ) {
            return false;
        }

        return is_dir("{$filepath}/.git");
    }

    /**
     * @param string $path
     * @param array $linksPaths
     * @return bool
     */
    private function isInLinks(string $path, array $linksPaths): bool
    {
        $links = array_keys($linksPaths);
        foreach ($links as $link) {
            if (strpos($path, $link) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string> $linksPaths
     * @return bool
     */
    private function copyLinks(array $linksPaths): bool
    {
        $git = new GitProcess($this->io);
        $all = 0;
        $copied = 0;
        foreach ($linksPaths as $link => $target) {
            $real = realpath($link);
            $targetParent = dirname($target);
            $saveIn = "{$targetParent}/" . pathinfo($real, PATHINFO_FILENAME) . '.zip';
            $git->cd($real)->exec("archive --format zip --output {$saveIn} master");
            $all++;
            if (file_exists($saveIn)) {
                $unzipped = $this->extractZip($saveIn);
                (unlink($saveIn) && $unzipped) and $copied++;
            }
        }

        return $all === $copied;
    }

    /**
     * @param string $zipPath
     * @return bool
     */
    private function extractZip(string $zipPath): bool
    {
        $folderName = pathinfo($zipPath, PATHINFO_FILENAME);
        $target = dirname($zipPath) . "/{$folderName}/";
        if (!$this->unzipper->unzip($zipPath, $target)) {
            $this->io->errorLine("Failed to copy {$folderName} package.");

            return false;
        }

        $contents = Finder::create()->in($target)->depth('== 0')->ignoreUnreadableDirs();
        /** @var SplFileInfo $item */
        foreach ($contents as $item) {
            $itemPath = $item->getPathname();
            if (!self::accept($itemPath)) {
                $item->isDir()
                    ? $this->filesystem->removeDirectory($itemPath)
                    : $this->filesystem->unlink($itemPath);
            }
        }

        return true;
    }
}
