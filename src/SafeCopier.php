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
        if (basename($path) === '.gitkeep') {
            return true;
        }

        return
            strpos($path, 'node_modules/') === false
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
        if (!is_dir($source)) {
            return self::accept($source) ? copy($source, $target) : false;
        }

        $this->filesystem->ensureDirectoryExists($target);
        $iterator = $this->copySourceIterator($source, $this->filesystem);

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
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

    /**
     * @param string $source
     * @param Filesystem $filesystem
     * @return \Iterator|\RecursiveDirectoryIterator
     */
    private function copySourceIterator(string $source, Filesystem $filesystem): \Iterator
    {
        return new class(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            ),
            $filesystem
        ) extends \FilterIterator {

            /**
             * @var Filesystem
             */
            private $filesystem;

            /**
             * @param \Iterator $iterator
             * @param Filesystem $filesystem
             */
            public function __construct(\Iterator $iterator, Filesystem $filesystem)
            {
                parent::__construct($iterator);
                $this->filesystem = $filesystem;
            }

            /**
             * @return bool
             *
             * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
             */
            public function accept()
            {
                // phpcs:enable

                /** @var \RecursiveDirectoryIterator $it */
                $it = $this->getInnerIterator();

                return SafeCopier::accept($this->filesystem->normalizePath($it->getSubPathname()));
            }
        };
    }
}
