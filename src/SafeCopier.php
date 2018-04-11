<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the vip-composer-plugin package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Inpsyde\VipComposer;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;

class SafeCopier
{
    /**
     * @var Filesystem
     */
    private $filesystem;
    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param IOInterface $io
     * @param Config $config
     * @return SafeCopier
     */
    public static function create(IOInterface $io, Config $config): SafeCopier
    {
        return new static(new Filesystem(), $io, $config);
    }

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
            'phpcs.xml.dist',
            'phpcs.xml',
            'phpunit.xml.dist',
            'phpunit.xml',
            'README.md',
            '._compiled-resources',
            'node_modules',
        ];

        $excludeExt = [
            'lock',
            'log',
            'error',
            'tmp',
            'temp',
        ];

        return
            !in_array($basename, $exclude, true)
            && !in_array($ext, $excludeExt, true)
            && strpos($path, 'node_modules/') === false
            && (strpos($path, '/.git') === false || $basename === '.gitkeep');
    }

    /**
     * @param Filesystem $filesystem
     * @param IOInterface $io
     * @param Config $config
     */
    public function __construct(Filesystem $filesystem, IOInterface $io, Config $config)
    {
        $this->filesystem = $filesystem;
        $this->io = $io;
        $this->config = $config;
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

        if (!$this->filesystem->isSymlinkedDirectory($filepath)
            && ! $this->filesystem->isJunction($filepath)
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
        $unzipper = new Unzipper($this->io, $this->config);
        foreach ($linksPaths as $link => $target) {
            $real = realpath($link);
            $targetParent = dirname($target);
            $saveIn = "{$targetParent}/" . pathinfo($real, PATHINFO_FILENAME) . '.zip';
            $git->cd($real)->exec("archive --format zip --output {$saveIn} master");
            if (file_exists($saveIn)) {
                $this->extractZip($saveIn, $unzipper);
                unlink($saveIn);
            }
        }

        return true;
    }

    /**
     * @param string $zipPath
     * @param Unzipper $unzipper
     */
    private function extractZip(string $zipPath, Unzipper $unzipper)
    {
        $target = dirname($zipPath) . '/' . pathinfo($zipPath, PATHINFO_FILENAME) . '/';
        $unzipper->unzip($zipPath, $target);
    }
}
