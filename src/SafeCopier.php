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

use Composer\Util\Filesystem;

class SafeCopier
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @return SafeCopier
     */
    public static function create(): SafeCopier
    {
        return new static(new Filesystem());
    }

    /**
     * @param string $path
     * @return bool
     */
    public static function accept(string $path): bool
    {
        $basename = basename($path);
        if ($basename === '.gitkeep') {
            return true;
        }

        $exclude = [
            'bitbucket-pipelines.yml',
            'phpcs.xml.dist',
            'phpcs.xml',
            'phpunit.xml.dist',
            'phpunit.xml',
            'README.md',
            '._compiled-resources',
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
            && !in_array(pathinfo($path, PATHINFO_EXTENSION), $excludeExt, true)
            && strpos($path, 'node_modules/') === false
            && strpos($path, '/.git') === false;
    }

    /**
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
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

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!self::accept($this->filesystem->normalizePath((string)$file))) {
                continue;
            }
            $targetPath = "{$target}/" . $iterator->getSubPathname();
            if ($file->isDir()) {
                $this->filesystem->ensureDirectoryExists($targetPath);
                continue;
            }

            $result = copy($file->getPathname(), $targetPath);
            if (!$result) {
                return false;
            }
        }

        return true;
    }
}
