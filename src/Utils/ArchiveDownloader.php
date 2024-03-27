<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Utils;

use Composer\Downloader\DownloaderInterface;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;
use Composer\Util\Loop;
use Composer\Util\SyncHelper;
use Inpsyde\VipComposer\Io;
use Symfony\Component\Finder\Finder;

class ArchiveDownloader
{
    public const ZIP = 'zip';
    public const RAR = 'rar';
    public const XZ = 'xz'; // phpcs:ignore
    public const TAR = 'tar';

    public function __construct(
        private Loop $loop,
        private DownloaderInterface $downloader,
        private Io $io,
        private Filesystem $filesystem
    ) {
    }

    /**
     * @param PackageInterface $package
     * @param string $path
     * @return bool
     */
    public function download(PackageInterface $package, string $path): bool
    {
        $tempDir = dirname($path) . '/.tmp' . substr(md5(uniqid($path, true)), 0, 8);
        try {
            $this->filesystem->ensureDirectoryExists($tempDir);
            SyncHelper::downloadAndInstallPackageSync(
                $this->loop,
                $this->downloader,
                $tempDir,
                $package
            );
            $this->filesystem->ensureDirectoryExists($path);

            $finder = new Finder();
            $finder->in($tempDir)->ignoreVCS(true)->ignoreUnreadableDirs()->depth('== 0');

            $errors = 0;
            /** @var \Symfony\Component\Finder\SplFileInfo $item */
            foreach ($finder as $item) {
                $basename = $item->getBasename();
                $targetPath = $this->filesystem->normalizePath("{$path}/{$basename}");
                if (is_dir($targetPath) || is_file($targetPath)) {
                    $this->filesystem->remove($targetPath);
                }

                $this->filesystem->copy($item->getPathname(), $targetPath) or $errors++;
            }

            return $errors === 0;
        } catch (\Throwable $throwable) {
            $this->io->verboseError($throwable->getMessage());

            return false;
        } finally {
            $this->filesystem->removeDirectory($tempDir);
        }
    }
}
