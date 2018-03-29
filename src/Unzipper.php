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
use Composer\Downloader\ZipDownloader;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Composer provides ZipDownloader class which extends ArchiveDownloader which in turn
 * extends FileDownloader.
 * So ZipDownloader is a "downloader", but we need just an "unzipper".
 *
 * This class exists because the `extract()` method of ZipDownloader, that is the only one we need,
 * is protected, so we need a subclass to access it.
 *
 * This class is final because four levels of inheritance are definitively enough.
 */
final class Unzipper extends ZipDownloader
{

    public function __construct(IOInterface $io, Config $config)
    {
        parent::__construct($io, $config);
    }

    /**
     * Unzip a given zip file to given target path.
     *
     * @param string $zipPath
     * @param string $target
     */
    public function unzip(string $zipPath, string $target)
    {
        $this->checkLibrary($zipPath);
        parent::extract($zipPath, $target); // phpcs:ignore
    }

    /**
     * This this just un unzipper, we don't download anything.
     *
     * @param PackageInterface $package
     * @param string $path
     * @param bool $output
     * @return string|void
     *
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
     */
    public function download(PackageInterface $package, $path, $output = true)
    {
        // phpcs:enable
    }

    /**
     * Check that system unzip command or ZipArchive class is available.
     *
     * Parent class do this in `download()` method that we can't use because it needs a package
     * instance that we don't have and it runs an actual file download that we don't need.
     *
     * @param string $zipPath
     */
    private function checkLibrary(string $zipPath)
    {
        $hasSystemUnzip = (new ExecutableFinder())->find('unzip');

        if (!$hasSystemUnzip && !class_exists('ZipArchive')) {
            $name = basename($zipPath);
            throw new \RuntimeException(
                "Can't unzip '{$name}' because your system does not support unzip."
            );
        }
    }
}
