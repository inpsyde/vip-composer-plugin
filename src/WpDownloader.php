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

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Composer\Util\Filesystem;
use Composer\Util\RemoteFilesystem;

class WpDownloader
{
    const RELEASES_URL = 'https://api.wordpress.org/core/version-check/1.7/';
    const DOWNLOADS_BASE_URL = 'https://downloads.wordpress.org/release/wordpress-';

    /**
     * @var array
     */
    private static $done = [];

    /**
     * @var array
     */
    private $config;

    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var RemoteFilesystem
     */
    private $remoteFilesystem;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var bool
     */
    private $isUpdate = false;

    /**
     * @param array $config
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function __construct(array $config, Composer $composer, IOInterface $io)
    {
        $this->config = $config;
        $this->composer = $composer;
        $this->io = $io;
        $this->remoteFilesystem = new RemoteFilesystem($io, $composer->getConfig());
        $this->filesystem = new Filesystem();
    }

    /**
     * This is triggered before _each_ package is installed.
     *
     * When the package is a Composer plugin, it does nothing. Since plugins are always all placed
     * on top of the dependencies stack doing this we ensure that the login in this method runs
     * when all the plugins are installed activated and very likely all the custom installers
     * are installed.
     * This is because we want to remove all the installers capable to install WordPress core
     * and replace them with our "CoreInstaller" that actually do nothing, avoiding that core
     * packages are installed at all: we don't need them since we are already downloading WP
     * from wp.org repo.
     *
     * Besides of this, during the very first installation, `install()` method is not triggered,
     * because Composer can't know about it _before_ this package is actually installed.
     * This is why in that case, and only in that case, we use this method to also trigger
     * installation.
     *
     * @param PackageEvent $event
     *
     * @see NoopCoreInstaller
     */
    public function prePackage(PackageEvent $event)
    {
        if (in_array(__FUNCTION__, self::$done, true)) {
            return;
        }

        $operation = $event->getOperation();
        $package = null;
        $operation instanceof UpdateOperation and $package = $operation->getTargetPackage();
        $operation instanceof InstallOperation and $package = $operation->getPackage();

        if (!$package || $package->getType() === 'composer-plugin') {
            return;
        }

        $manager = $event->getComposer()->getInstallationManager();
        $fakeInstaller = new NoopCoreInstaller($event->getIO());
        $manager->addInstaller($fakeInstaller);

        self::$done[] = __FUNCTION__;

        if (!in_array('install', self::$done, true)) {
            /** @var callable $method */
            $method = [$this, $operation->getJobType()];
            $method();
        }
    }

    /**
     * Setup `$isUpdate` flag to true, then just run `WpDownloader::install()`
     *
     * @see WpDownloader::install()
     */
    public function update()
    {
        $this->isUpdate = true;
        $this->install();
    }

    /**
     * Hooked on installer event, it setups configs, installed and target version
     * then launches the download of WP when needed.
     */
    public function install()
    {
        if (in_array(__FUNCTION__, self::$done, true)) {
            return;
        }

        self::$done[] = __FUNCTION__;

        $targetVersion = $this->discoverTargetVersion();
        $installedVersion = $this->discoverInstalledVersion();
        $version = $this->resolveTargetVersion($targetVersion);

        if (!$this->shouldInstall($targetVersion, $installedVersion)) {
            $this->write("\n<info>VIP: No need to download WordPress:</info>");
            $this->write("<info>    installed version matches required version.</info>\n");

            list($target) = $this->preparePaths($version);
            $this->copyWpConfig($target);

            return;
        }

        list($target, $targetTemp, $zipUrl, $zipFile) = $this->preparePaths($version);
        $info = $version;

        $this->write("VIP: Installing <info>WordPress</info> (<comment>{$info}</comment>)", true);

        $this->cleanUp($zipFile, $target, $targetTemp);

        if (!$this->remoteFilesystem->copy('wordpress.org', $zipUrl, $zipFile)) {
            throw new \RuntimeException(
                sprintf(
                    'Error downloading WordPress %s from %s',
                    $version,
                    $zipUrl
                )
            );
        }

        if (!is_file($zipFile)) {
            throw new \RuntimeException(
                sprintf('Error downloading WordPress %s from %s', $version, $zipUrl)
            );
        }

        $this->filesystem->ensureDirectoryExists($target);

        $unzipper = new Unzipper($this->io, $this->composer->getConfig());

        $this->write('   Unzipping...');
        $unzipper->unzip($zipFile, $targetTemp);
        $this->filesystem->unlink($zipFile);
        $this->write('   Moving to destination folder...');
        $this->filesystem->copyThenRemove("{$targetTemp}/wordpress", $target);
        $this->filesystem->removeDirectory($targetTemp);

        $this->write("\n<info>VIP: WordPress {$version} installed.</info>\n", true);

        $this->copyWpConfig($target);
    }

    /**
     * @param string $target
     */
    private function copyWpConfig(string $target)
    {
        $parent = dirname($target);
        if (file_exists("{$parent}/wp-config.php")) {
            $this->write("\n<comment>VIP: wp-config.php already exists.</comment>", true);

            return;
        }

        $wpConfigSource = file_exists("{$target}/wp-config.php")
            ? "{$target}/wp-config.php"
            : "{$target}/wp-config-sample.php";

        if (!file_exists($wpConfigSource)) {
            $this->write(
                "\n<error>VIP: Cannot copy wp-config.php, '{$wpConfigSource}' not found.</error>",
                true
            );

            return;
        }

        if (!$this->filesystem->copy($wpConfigSource, "{$parent}/wp-config.php")) {
            $this->write(
                "\n<error>VIP: Cannot copy '{$wpConfigSource}' to '{$parent}/wp-config.php'.</error>",
                true
            );

            return;
        }

        $this->write(
            "\n<info>VIP: wp-config.php copied, you probably need to configure it.</info>",
            true
        );
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
        $cwd = rtrim(getcwd(), '\\/');

        $targetDir = ltrim($this->config['target-dir'] ?? '', '\\/');
        $target = $this->filesystem->normalizePath("{$cwd}/{$targetDir}");

        $parent = dirname($targetDir);
        $targetTempSubdir = $parent === '.'
            ? "/.{$targetDir}"
            : "{$parent}/." . basename($targetDir);

        $targetTemp = $this->filesystem->normalizePath("{$cwd}/{$targetTempSubdir}");

        $zipUrl = self::DOWNLOADS_BASE_URL . "{$version}-no-content.zip";

        $zipFile = $cwd . '/' . basename(parse_url($zipUrl, PHP_URL_PATH));
        $zipFile = $this->filesystem->normalizePath($zipFile);

        return [$target, $targetTemp, $zipUrl, $zipFile];
    }

    /**
     * @param string $zipFile
     * @param string $target
     * @param string $targetTemp
     */
    private function cleanUp(string $zipFile, string $target, string $targetTemp)
    {
        $this->write('<info>VIP: Cleaning previous WordPress in files...</info>');

        // Delete leftover zip file if found
        file_exists($zipFile) and $this->filesystem->unlink($zipFile);

        // Delete leftover unzip temp folder if found
        is_dir($targetTemp) and $this->filesystem->removeDirectory($targetTemp);

        // Delete WordPress wp-includes folder if found
        is_dir("{$target}/wp-includes") and $this->filesystem->removeDirectory("{$target}/wp-includes");

        // Delete WordPress wp-admin folder if found
        is_dir("{$target}/wp-admin") and $this->filesystem->removeDirectory("{$target}/wp-admin");

        // Delete all files in WordPress root, skipping wp-config.php if there
        $files = glob("{$target}/*.*");
        foreach ($files as $file) {
            if (is_file($file) && basename($file) !== 'wp-config.php') {
                $this->filesystem->unlink($file);
            }
        }
    }

    /**
     * Look in filesystem to find an installed version of WordPress and discover its version.
     *
     * @return string
     */
    private function discoverInstalledVersion(): string
    {
        $targetDir = $this->config['target-dir'] ?? '';
        $dir = $this->filesystem->normalizePath(getcwd() . "/{$targetDir}");

        if (!is_dir($dir)) {
            return '';
        }

        $versionFile = "{$dir}/wp-includes/version.php";
        if (!is_file($versionFile) || !is_readable($versionFile)) {
            return '';
        }

        $wp_version = '';
        /** @noinspection PhpIncludeInspection */
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
     * Looks config in `composer.json` to see which version (or version range) is required.
     * If nothing is set (or something wrong is set) the last available WordPress version
     * is returned.
     *
     * @return string
     * @see WpDownloader::queryLastVersion()
     * @see WpDownloader::discoverWpPackageVersion()
     */
    private function discoverTargetVersion(): string
    {
        $version = trim((string)($this->config['version'] ?? ''));

        if ($version === 'latest' || $version === '*') {
            return $this->queryLastVersion();
        }

        if (!$version) {
            $wpPackageVer = $this->discoverWpPackageVersion();
            $wpPackageVer and $version = $wpPackageVer;
        }

        $fixedTargetVersion = preg_match('/^[3|4]\.([0-9]){1}(\.[0-9])?+$/', $version) > 0;

        return $fixedTargetVersion ? $this->normalizeVersionWp($version) : trim($version);
    }

    /**
     * Looks config in `composer.json` for any wordpress core package and return the first found.
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
        $repo = $this->composer->getRepositoryManager();

        /** @var Link $link */
        foreach ($rootPackage->getRequires() as $link) {
            $constraint = $link->getConstraint();
            if ($constraint === null) {
                continue;
            }
            $package = $repo->findPackage($link->getTarget(), $constraint);
            if ($package && $package->getType() === 'wordpress-core') {
                $wpConstraint = $constraint->getPrettyString();

                return $wpConstraint;
            }
        }

        return '';
    }

    /**
     * Query wp.org API to get the last version.
     *
     * @return string
     *
     * @throws \RuntimeException in case of API errors
     *
     * @see WpDownloader::queryVersions()
     */
    private function queryLastVersion(): string
    {
        $versions = $this->queryVersions();
        if (!$versions) {
            throw new \RuntimeException(
                'Could not resolve available WordPress versions from wp.org API.'
            );
        }

        return reset($versions);
    }

    /**
     * Query wp.org API to get available versions for download.
     *
     * @return string[]
     */
    private function queryVersions(): array
    {
        static $versions;
        if (is_array($versions)) {
            return $versions;
        }

        $this->write('<info>VIP: Retrieving WordPress versions info...</info>');
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
            $data = @json_decode($content, true);
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
     * Return true if based on current setup (target and installed ver, update or install context)
     * a new version should be downloaded from wp.org.
     *
     * @param string $targetVersion
     * @param string $installedVersion
     * @return bool
     */
    private function shouldInstall(string $targetVersion, string $installedVersion): bool
    {
        if (!$installedVersion) {
            return true;
        }

        if (Comparator::equalTo($installedVersion, $targetVersion)) {
            return false;
        }

        if (!$this->isUpdate && Semver::satisfies($installedVersion, $targetVersion)) {
            return false;
        }

        $resolved = $this->resolveTargetVersion($targetVersion);

        return $resolved !== $installedVersion;
    }

    /**
     * Resolve a range of target versions into a canonical version.
     *
     * E.g. ">=4.5" is resolved in something like "4.6.1"
     *
     * @param string $version
     *
     * @return string
     */
    private function resolveTargetVersion(string $version): string
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

        $versions = $this->queryVersions();

        if (!$versions) {
            throw new \RuntimeException(
                'Could not resolve available WordPress versions from wp.org API.'
            );
        }

        $satisfied = Semver::satisfiedBy($versions, $version);

        if (!$satisfied) {
            throw new \RuntimeException(
                sprintf("No WordPress available version satisfies requirements '{$version}'.")
            );
        }

        $satisfied = Semver::rsort($satisfied);
        $last = reset($satisfied);
        $resolved[$version] = $this->normalizeVersionWp($last);

        return $resolved[$version];
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

    /**
     * Wrapper around Composer `IO::write`, only write give message IO is verbose or
     * `$force` param is true.
     *
     * @param string $message
     * @param bool $force
     */
    private function write(string $message, bool $force = false)
    {
        if ($force || $this->io->isVerbose()) {
            $this->io->write($message);
        }
    }
}
