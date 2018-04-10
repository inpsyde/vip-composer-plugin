<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the vip-composer-plugin package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.NoAccessors
 */

declare(strict_types=1);

namespace Inpsyde\VipComposer;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Installer\PackageEvents;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use Composer\Util\Platform;

class Plugin implements PluginInterface, EventSubscriberInterface, Capable, CommandProvider
{
    const NAME = 'inpsyde/vip-composer-plugin';
    const CONFIG_KEY = 'vip-composer';
    const VIP_TARGET_DIR_KEY = 'vip-dir';
    const DEFAULT_VIP_TARGET = 'vip';
    const VIP_CONFIG_KEY = 'config-files';
    const VIP_CONFIG_DIR_KEY = 'dir';
    const VIP_CONFIG_LOAD_KEY = 'load';
    const VIP_GIT_KEY = 'git';
    const VIP_GIT_URL_KEY = 'url';
    const VIP_GIT_BRANCH_KEY = 'branch';
    const CUSTOM_PATHS_KEY = 'content-dev';
    const CUSTOM_MUPLUGINS_KEY = 'muplugins';
    const CUSTOM_PLUGINS_KEY = 'plugins';
    const CUSTOM_THEMES_KEY = 'themes';
    const CUSTOM_LANGUAGES_KEY = 'languages';
    const NO_GIT = 1;
    const DO_GIT = 2;
    const DO_PUSH = 4;

    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var array
     */
    private $extra;

    /**
     * @var Directories
     */
    private $dirs;

    /**
     * @var Installer
     */
    private $installer;

    /**
     * @var WpDownloader
     */
    private $wpDownloader;

    /**
     * @var VipGoMuDownloader
     */
    private $vipMuDownloader;

    /**
     * @var array
     */
    private $wpDownloaderConfig = [];

    /**
     * @var VipGit
     */
    private $git;

    /**
     * @var int
     */
    private $flags = self::NO_GIT;

    /**
     * @var string
     */
    private $remoteUrl;

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::PRE_INSTALL_CMD => 'wpInstall',
            ScriptEvents::PRE_UPDATE_CMD => 'wpUpdate',
            ScriptEvents::POST_INSTALL_CMD => 'run',
            ScriptEvents::POST_UPDATE_CMD => 'run',
            PackageEvents::PRE_PACKAGE_INSTALL => ['prePackage', PHP_INT_MAX],
            PackageEvents::PRE_PACKAGE_UPDATE => ['prePackage', PHP_INT_MAX],
            PackageEvents::PRE_PACKAGE_UNINSTALL => ['prePackage', PHP_INT_MAX],
        ];
    }

    /**
     * @param int $flags
     * @param string|null $remoteUrl
     * @return Plugin
     */
    public static function forCommand(int $flags = self::DO_GIT, string $remoteUrl = null): Plugin
    {
        $instance = new static();
        $instance->flags = $flags;
        $instance->remoteUrl = $remoteUrl;

        return $instance;
    }

    /**
     * @inheritdoc
     */
    public function getCapabilities()
    {
        return [CommandProvider::class => __CLASS__];
    }

    /**
     * @inheritdoc
     */
    public function getCommands()
    {
        return [new Command()];
    }

    /**
     * @inheritdoc
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
        $extra = $composer->getPackage()->getExtra();
        $this->extra = (array)($extra[self::CONFIG_KEY] ?? []);

        $vipDir = $this->extra[self::VIP_TARGET_DIR_KEY] ?? self::DEFAULT_VIP_TARGET;

        $this->dirs = new Directories(new Filesystem(), Platform::expandPath($vipDir), getcwd());
        $this->installer = new Installer($this->dirs, $composer, $this->io);
        $this->wpDownloaderConfig = $this->wpDownloaderConfig();
        $this->wpDownloader = new WpDownloader($this->wpDownloaderConfig, $composer, $this->io);
        $this->vipMuDownloader = new VipGoMuDownloader($this->io, $this->dirs);

        $composer->getInstallationManager()->addInstaller($this->installer);
    }

    /**
     * @param PackageEvent $event
     */
    public function prePackage(PackageEvent $event)
    {
        $operation = $event->getOperation();
        $package = null;
        $operation instanceof UpdateOperation and $package = $operation->getTargetPackage();
        $operation instanceof InstallOperation and $package = $operation->getPackage();

        if ($package && $package->getType() !== 'composer-plugin') {
            $event->getComposer()->getInstallationManager()->addInstaller($this->installer);
            $this->wpDownloader->prePackage($event);
            $this->vipMuDownloader->download();
        }
    }

    /**
     * @param Event|null $event
     * @return int
     */
    public function run(Event $event = null): int
    {
        if (!file_exists($this->dirs->basePath() . '/composer.lock')) {
            $this->io->writeError('<error>VIP: "composer.lock" not found.</error>');
            if (!$event) {
                $this->io->writeError('<error>Please install or update via Composer first.</error>');
            }

            return 1;
        }

        $this->dirs->createDirs();
        $this->vipMuDownloader->download();

        $contentDir = "/{$this->wpDownloaderConfig['target-dir']}/wp-content/";
        $contentDirPath = $this->dirs->basePath() . $contentDir;
        $this->io->write("<info>VIP: Symlinking content to {$contentDirPath}...</info>");
        $this->dirs->symlink($contentDirPath);

        $filesystem = new Filesystem();
        $config = $this->composer->getConfig();
        /** @var \Composer\Package\PackageInterface[] $package */
        $packages = $this->composer->getRepositoryManager()->getLocalRepository()->getPackages();
        $muGenerator = new MuPluginGenerator($this->dirs, $config, new PluginFileFinder($this->installer));
        $muGenerator->generate(...$packages);

        $customPathCopier = new CustomPathCopier($filesystem, $this->extra);
        $customPathCopier->copy($this->dirs, $this->io);

        $packages = new InstalledPackages($this->composer);

        $autoload = new AutoloadGenerator($packages, $this->dirs);
        $autoload->generate($this->composer, $this->io);

        $configSync = new ConfigSynchronizer($this->dirs, $this->io, $this->extra);
        $configSync->sync($filesystem, $this->wpDownloaderConfig['target-dir']);

        if (($this->flags & self::NO_GIT) === self::NO_GIT) {
            return 0;
        }

        $this->git = new VipGit(
            $this->io,
            $config,
            $this->dirs,
            $this->extra,
            $this->remoteUrl
        );

        $this->io->write('<info>VIP: Starting Git sync...</info>');
        $push = ($this->flags & self::DO_PUSH) === self::DO_PUSH;
        $push ? $this->git->push($filesystem, $packages) : $this->git->sync($filesystem, $packages);

        return 0;
    }

    /**
     * Download WP on installation.
     */
    public function wpInstall()
    {
        $this->wpDownloader->install();
        $this->vipMuDownloader->download();
    }

    /**
     * Download WP on update.
     */
    public function wpUpdate()
    {
        $this->wpDownloader->update();
        $this->vipMuDownloader->download();
    }

    /**
     * @return array
     */
    private function wpDownloaderConfig(): array
    {
        $default = [
            'version' => '',
            'target-dir' => '',
        ];

        $config = array_key_exists('wordpress', $this->extra) ? $this->extra['wordpress'] : [];
        $config = array_merge($default, $config);

        return $config;
    }
}
