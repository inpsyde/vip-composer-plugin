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

namespace Inpsyde\VipComposer\Task;

use Composer\Composer;
use Composer\Downloader\ZipDownloader;
use Composer\Package\Link;
use Composer\Package\Package;
use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Composer\Util\Filesystem;
use Composer\Util\RemoteFilesystem;
use Inpsyde\VipComposer\Config;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\Utils\Unzipper;
use Symfony\Component\Finder\Finder;

final class DownloadWpCore implements Task
{
    private const RELEASES_URL = 'https://api.wordpress.org/core/version-check/1.7/';
    private const DOWNLOADS_BASE_URL = 'https://downloads.wordpress.org/release/wordpress-';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var RemoteFilesystem
     */
    private $remoteFilesystem;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var ZipDownloader
     */
    private $zipDownloader;

    /**
     * @param Config $config
     * @param Composer $composer
     * @param RemoteFilesystem $remoteFilesystem
     * @param Filesystem $filesystem
     * @param ZipDownloader $zipDownloader
     */
    public function __construct(
        Config $config,
        Composer $composer,
        RemoteFilesystem $remoteFilesystem,
        Filesystem $filesystem,
        ZipDownloader $zipDownloader
    ) {

        $this->config = $config;
        $this->composer = $composer;
        $this->remoteFilesystem = $remoteFilesystem;
        $this->filesystem = $filesystem;
        $this->zipDownloader = $zipDownloader;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return 'Download WP core';
    }

    /**
     * @param TaskConfig $taskConfig
     * @return bool
     */
    public function enabled(TaskConfig $taskConfig): bool
    {
        return ($taskConfig->isLocal() || $taskConfig->forceCoreUpdate())
            && !$taskConfig->skipCoreUpdate();
    }

    /**
     * Hooked on installer event, it setups configs, installed and target version
     * then launches the download of WP when needed.
     *
     * @param Io $io
     * @param TaskConfig $taskConfig
     */
    public function run(Io $io, TaskConfig $taskConfig): void
    {
        $targetVersion = $this->discoverTargetVersion($io);
        $version = $targetVersion ? $this->resolveTargetVersion($targetVersion, $io) : '';
        if (!$version) {
            return;
        }

        if (
            !$taskConfig->forceCoreUpdate()
            && !$this->shouldInstall($targetVersion, $this->discoverInstalledVersion(), $io)
        ) {
            $io->infoLine(
                'No need to download WordPress: installed version matches required version.'
            );
            [$wpCoreDir] = $this->preparePaths($version);
            $this->copyWpConfig($wpCoreDir, $io);
            $this->createWpCliYml($wpCoreDir, $io);

            return;
        }

        [$wpCorePath, $zipUrl] = $this->preparePaths($version);

        $io->commentLine("Installing WordPress {$version}...");
        try {
            $package = new Package('wordpress/wordpress', $version, $version);
            $package->setDistType('zip');
            $package->setDistUrl($zipUrl);
            $this->zipDownloader->download($package, $wpCorePath, false);
        } catch (\Throwable $throwable) {
            $error = sprintf(
                'Error installing WordPress %s from %s.',
                $version,
                $zipUrl
            );

            $io->errorLine($error);
            $io->verboseErrorLine($throwable->getMessage());

            return;
        }

        $io->infoLine("WordPress {$version} installed.");
        $this->copyWpConfig($wpCorePath, $io);
        $this->createWpCliYml($wpCorePath, $io);
    }

    /**
     * Looks config in `composer.json` to see which version (or version range) is required.
     * If nothing is set (or something wrong is set) the last available WordPress version
     * is returned.
     *
     * @param Io $io
     * @return string
     * @see DownloadWpCore::queryLastVersion()
     * @see DownloadWpCore::discoverWpPackageVersion()
     */
    private function discoverTargetVersion(Io $io): string
    {
        $version = $this->config->wpConfig()[Config::WP_VERSION_KEY];

        if ($version === 'latest' || $version === '*') {
            return $this->queryLastVersion($io);
        }

        if (!$version) {
            $wpPackageVer = $this->discoverWpPackageVersion();
            $wpPackageVer and $version = $wpPackageVer;
        }

        $fixedTargetVersion = preg_match('/^[3|4]\.([0-9]){1}(\.[0-9])?+$/', $version) > 0;

        return $fixedTargetVersion ? $this->normalizeVersionWp($version) : trim($version);
    }

    /**
     * Look in filesystem to find an installed version of WordPress and discover its version.
     *
     * @return string
     */
    private function discoverInstalledVersion(): string
    {
        $baseDir = $this->config->basePath();
        $wpCoreDir = $this->config->wpConfig()[Config::WP_LOCAL_DIR_KEY];
        $wpCorePath = $this->filesystem->normalizePath("{$baseDir}/{$wpCoreDir}");

        if (!is_dir($wpCorePath)) {
            return '';
        }

        $versionFile = "{$wpCorePath}/wp-includes/version.php";
        if (!is_file($versionFile) || !is_readable($versionFile)) {
            return '';
        }

        $wp_version = '';
        require $versionFile;

        if (!$wp_version) {
            return '';
        }

        try {
            return $this->normalizeVersionWp($wp_version);
        } catch (\UnexpectedValueException $version) {
            return '';
        }
    }

    /**
     * Looks config in `composer.json` for any WordPress core package and return the first found.
     *
     * This used to set the version to download if no wp-downloader specific configs are set.
     *
     * @return string
     */
    private function discoverWpPackageVersion(): string
    {
        static $wpConstraint;
        if (is_string($wpConstraint)) {
            return $wpConstraint;
        }

        $rootPackage = $this->composer->getPackage();
        $repositoryManager = $this->composer->getRepositoryManager();

        /** @var Link $link */
        foreach ($rootPackage->getRequires() as $link) {
            $constraint = $link->getConstraint();
            if ($constraint === null) {
                continue;
            }
            $package = $repositoryManager->findPackage($link->getTarget(), $constraint);
            if ($package && $package->getType() === 'wordpress-core') {
                $wpConstraint = $constraint->getPrettyString();

                return $wpConstraint;
            }
        }

        return '';
    }

    /**
     * Resolve a range of target versions into a canonical version.
     *
     * E.g. ">=4.5" is resolved in something like "4.6.1"
     *
     * @param string $version
     *
     * @param Io $io
     * @return string
     */
    private function resolveTargetVersion(string $version, Io $io): string
    {
        static $resolved = [];

        if (array_key_exists($version, $resolved)) {
            return $resolved[$version];
        }

        $exact = preg_match('|^[0-9]+\.[0-9]{1}(\.[0-9]+)*(\.[0-9]+)*$|', $version);

        if ($exact) {
            $resolved[$version] = $this->normalizeVersionWp($version);
            // good luck
            return $resolved[$version];
        }

        $versions = $this->queryVersions($io);

        if (!$versions) {
            $io->errorLine('Could not resolve available WordPress versions from wp.org API.');

            return '';
        }

        $satisfied = Semver::satisfiedBy($versions, $version);

        if (!$satisfied) {
            $io->errorLine("No WordPress available version satisfies requirements '{$version}'.");

            return '';
        }

        $satisfied = Semver::rsort($satisfied);
        $last = reset($satisfied);
        $resolved[$version] = $this->normalizeVersionWp($last);

        return $resolved[$version];
    }

    /**
     * Return true if based on current setup (target and installed ver, update or install context)
     * a new version should be downloaded from wp.org.
     *
     * @param string $targetVersion
     * @param string $installedVersion
     * @param Io $io
     * @return bool
     */
    private function shouldInstall(string $targetVersion, string $installedVersion, Io $io): bool
    {
        if (!$installedVersion) {
            return true;
        }

        if (Comparator::equalTo($installedVersion, $targetVersion)) {
            return false;
        }

        if (Semver::satisfies($installedVersion, $targetVersion)) {
            return false;
        }

        $resolved = $this->resolveTargetVersion($targetVersion, $io);

        return $resolved && ($resolved !== $installedVersion);
    }

    /**
     * Build the paths to install WP package.
     * Cleanup existent paths.
     *
     * @param string $version
     * @return array
     */
    private function preparePaths(string $version): array
    {
        $baseDir = $this->config->basePath();
        $wpCoreDir = $this->config->wpConfig()[Config::WP_LOCAL_DIR_KEY];
        $wpCorePath = $this->filesystem->normalizePath("{$baseDir}/{$wpCoreDir}");

        $zipUrl = self::DOWNLOADS_BASE_URL . "{$version}-no-content.zip";

        return [$wpCorePath, $zipUrl];
    }

    /**
     * @param string $coreDir
     * @param Io $io
     */
    private function copyWpConfig(string $coreDir, Io $io): void
    {
        $io->commentLine("Copying 'wp-config.php' from core folder...");

        $parent = dirname($coreDir);
        if (file_exists("{$parent}/wp-config.php")) {
            $io->infoLine('"wp-config.php" already exists in target folder, nothing to copy.');

            return;
        }

        $wpConfigSource = file_exists("{$coreDir}/wp-config.php")
            ? "{$coreDir}/wp-config.php"
            : "{$coreDir}/wp-config-sample.php";

        if (!file_exists($wpConfigSource)) {
            $io->errorLine("Cannot copy 'wp-config.php': '{$wpConfigSource}' not found.");

            return;
        }

        if (!$this->filesystem->copy($wpConfigSource, "{$parent}/wp-config.php")) {
            $io->errorLine("Failed copying '{$wpConfigSource}' to '{$parent}/wp-config.php'");

            return;
        }

        $io->infoLine('wp-config.php copied, you probably need to configure it.');
    }

    /**
     * @param string $coreDir
     * @param Io $io
     * @return void
     */
    private function createWpCliYml(string $coreDir, Io $io): void
    {
        $io->commentLine('Creating wp-cli.yml...');
        $path = $this->filesystem->findShortestPath($this->config->basePath(), $coreDir);
        if (file_put_contents($this->config->basePath() . '/wp-cli.yml', "path: {$path}\n")) {
            $io->infoLine('wp-cli.yml written');
            return;
        }

        $io->errorLine('Failed writing /wp-cli.yml.');
    }

    /**
     * @param string $zipFile
     * @param string $wpCoreDir
     * @param Io $io
     */
    private function cleanUp(string $zipFile, string $wpCoreDir, Io $io): void
    {
        $io->verboseCommentLine('Cleaning previous WordPress files...');

        // Delete leftover zip file if found
        if (file_exists($zipFile)) {
            $this->filesystem->unlink($zipFile);
        }

        $dirs = (new Finder())
            ->in($wpCoreDir)
            ->depth('== 0')
            ->ignoreDotFiles(true);

        foreach ($dirs as $dir) {
            $this->filesystem->remove($dir->getPathname());
        }
    }

    /**
     * Query wp.org API to get the last version.
     *
     * @param Io $io
     * @return string
     *
     * @see DownloadWpCore::queryVersions()
     */
    private function queryLastVersion(Io $io): string
    {
        $versions = $this->queryVersions($io);
        if (!$versions) {
            $io->errorLine('Could not resolve available WordPress versions from wp.org API.');

            return '';
        }

        return reset($versions);
    }

    /**
     * Query wp.org API to get available versions for download.
     *
     * @param Io $io
     * @return string[]
     */
    private function queryVersions(Io $io): array
    {
        static $versions;
        if (is_array($versions)) {
            return $versions;
        }

        $io->verboseCommentLine('Retrieving WordPress versions info...');
        $content = $this->remoteFilesystem->getContents('wordpress.org', self::RELEASES_URL, false);
        $code = $this->remoteFilesystem->findStatusCode($this->remoteFilesystem->getLastHeaders());
        if ($code !== 200) {
            return [];
        }

        // phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
        $extractVer = function ($package): string {
            // phpcs:enable
            if (!is_array($package) || empty($package['version'])) {
                return '';
            }

            return $this->normalizeVersionWp($package['version']);
        };

        try {
            $data = json_decode($content, true);
            if (!$data || !is_array($data) || empty($data['offers'])) {
                return [];
            }

            $parsed = array_unique(array_filter(array_map($extractVer, (array)$data['offers'])));
            $versions = $parsed ? Semver::rsort($parsed) : [];

            return $versions;
        } catch (\Exception $exception) {
            return [];
        }
    }

    /**
     * Normalize a version string in the form x.x.x (where "x" is an integer)
     * because Composer semver normalization returns versions in the form  x.x.x.x
     * Moreover, things like x.x.0 are converted to x.x, because WordPress skip zeroes for
     * minor versions.
     *
     * @param string $version
     * @return string
     */
    private function normalizeVersionWp(string $version): string
    {
        $beta = explode('-', trim($version, ". \t\n\r\0\x0B"), 2);
        $stable = $beta[0];

        $pieces = explode('.', preg_replace('/[^0-9\.]/', '', $stable));
        $pieces = array_map('intval', $pieces);
        isset($pieces[0]) or $pieces[0] = 0;
        isset($pieces[1]) or $pieces[1] = 0;
        if ($pieces[1] > 9) {
            $str = (string)$pieces[1];
            $pieces[1] = $str[0];
        }
        if (empty($pieces[2])) {
            return "{$pieces[0]}.{$pieces[1]}";
        }
        if ($pieces[2] > 9) {
            $str = (string)$pieces[1];
            $pieces[2] = $str[0];
        }

        return "{$pieces[0]}.{$pieces[1]}.{$pieces[2]}";
    }
}
