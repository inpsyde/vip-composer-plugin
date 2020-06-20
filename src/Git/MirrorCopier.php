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

class MirrorCopier
{

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
        $basename = basename($path);
        $ext = pathinfo($path, PATHINFO_EXTENSION);

        $exclude = [
            'bitbucket-pipelines.yml',
            'changelog.md',
            'changelog.txt',
            'composer.json',
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

        $excludeExt = [
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
        ];

        $nestedVendorRegEx = '~client-mu-plugins/vendor/[^/]+/[^/]+/vendor/[^/]+~';

        return
            !in_array(strtolower($basename), $exclude, true)
            && !in_array(strtolower($ext), $excludeExt, true)
            && strpos($path, 'node_modules/') === false
            && ((strpos($basename, '.') !== 0) || $basename === '.gitkeep')
            && !preg_match($nestedVendorRegEx, str_replace('\\', '/', $path));
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
     * @param array $linksPaths
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
        $this->unzipper->unzip($zipPath, $target);
        if (!is_dir($target)) {
            $this->io->errorLine("Failed to copy {$folderName} package.");

            return false;
        }

        $files = glob("{$target}/*", GLOB_NOSORT);
        foreach ($files as $file) {
            if (!self::accept($file)) {
                is_dir($file)
                    ? $this->filesystem->removeDirectory($file)
                    : unlink($file);
            }
        }

        return true;
    }
}
