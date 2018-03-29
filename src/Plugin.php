<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the vip-composer-plugin package.
 *
 * (c) © 2018 UEFA. All rights reserved.
 */

declare(strict_types=1);

namespace Uefa\VipComposer;

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
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use Composer\Util\Platform;

class Plugin implements PluginInterface, EventSubscriberInterface, Capable, CommandProvider
{
    const NAME = 'uefa/vip-composer-plugin';
    const CONFIG_KEY = 'vip-composer';
    const VIP_TARGET_DIR_KEY = 'vip-dir';
    const VIP_CONFIG_KEY = 'vip-config';
    const VIP_CONFIG_DIR_KEY = 'dir';
    const VIP_CONFIG_LOAD_KEY = 'load';
    const VIP_GIT_KEY = 'vip-git';
    const VIP_GIT_URL_KEY = 'url';
    const VIP_GIT_BRANCH_KEY = 'branch';
    const CUSTOM_PATHS_KEY = 'content-dev';
    const CUSTOM_MUPLUGINS_KEY = 'muplugins';
    const CUSTOM_PLUGINS_KEY = 'plugins';
    const CUSTOM_THEMES_KEY = 'plugins-dir';
    const DEFAULT_VIP_TARGET = '.vip';
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
     * @var VipSkeleton
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
            ScriptEvents::PRE_INSTALL_CMD => 'wpDownloaderInstall',
            ScriptEvents::PRE_UPDATE_CMD => 'wpDownloaderUpdate',
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

        $this->dirs = new VipSkeleton(new Filesystem(), Platform::expandPath($vipDir), getcwd());
        $this->installer = new Installer($this->dirs, $composer, $this->io);
        $this->wpDownloaderConfig = $this->wpDownloaderConfig();
        $this->wpDownloader = new WpDownloader($this->wpDownloaderConfig, $composer, $this->io);
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
        }
    }

    /**
     * @throws \Exception
     */
    public function run()
    {
        $this->dirs->createDirs();

        $contentDir = "/{$this->wpDownloaderConfig['target-dir']}/wp-content/";
        $contentDirPath = getcwd() . $contentDir;
        $this->io->write("<info>VIP: Symlinking content to {$contentDirPath}...</info>");
        $this->dirs->symlink($contentDirPath);

        $this->io->write('<info>VIP: Building MU plugin...</info>');
        /** @var \Composer\Package\PackageInterface[] $package */
        $packages = $this->composer->getRepositoryManager()->getLocalRepository()->getPackages();
        $muGenerator = new MuPluginGenerator($this->dirs, new PluginFileFinder($this->installer));
        $muGenerator->generate(...$packages);

        $basePath = getcwd();
        $filesystem = new Filesystem();
        $config = $this->composer->getConfig();

        $customPathCopier = new CustomPathCopier($filesystem, $basePath, $this->extra);
        $customPathCopier->copy($this->dirs, $this->io);

        $packages = new InstalledPackages($this->composer);

        $autoload = new VipAutoloadGenerator($packages);
        $autoload->generate($this->composer, $this->io);

        $configSync = new ConfigSynchronizer($this->dirs, $this->io, $basePath, $this->extra);
        $configSync->sync($filesystem, $this->wpDownloaderConfig['target-dir']);

        if (($this->flags & self::NO_GIT) === self::NO_GIT) {
            return;
        }

        $this->git = new VipGit(
            $this->io,
            $config,
            $this->dirs->targetPath(),
            $this->extra,
            $this->remoteUrl
        );

        $this->io->write('<info>VIP: Starting Git sync...</info>');
        $push = ($this->flags & self::DO_PUSH) === self::DO_PUSH;
        $push ? $this->git->push($filesystem, $packages) : $this->git->sync($filesystem, $packages);
    }

    /**
     * Download WP on installation.
     */
    public function wpDownloaderInstall()
    {
        $this->wpDownloader->install();
    }

    /**
     * Download WP on update.
     */
    public function wpDownloaderUpdate()
    {
        $this->wpDownloader->update();
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
