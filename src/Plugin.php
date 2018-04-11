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
use Composer\Config;
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
    const NO_VIP_MU = 128;
    const DO_VIP_MU = 256;

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
     * @var array
     */
    private $wpDownloaderConfig = [];

    /**
     * @var int
     */
    private $flags = self::NO_GIT | self::NO_VIP_MU;

    /**
     * @var array
     */
    private $commandConfig = [];

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
     * @param array $config
     * @return Plugin
     */
    public static function forCommand(int $flags = self::DO_GIT, array $config = []): Plugin
    {
        $instance = new static();
        $instance->flags = $flags;
        $instance->commandConfig = $config;

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
     * @param Event|null $event
     * @return int
     */
    public function run(Event $event = null): int
    {
        // Setup some variables used by different tasks.
        $filesystem = new Filesystem();
        $config = $this->composer->getConfig();
        $packages = new InstalledPackages($this->composer);
        $shouldInstallVipMuPlugins = ($this->flags & self::DO_VIP_MU) === self::DO_VIP_MU;

        if ($this->checkComposerLock($event)
            && $this->installVipMuPlugins($shouldInstallVipMuPlugins)
            && $this->symlinkDirectories($shouldInstallVipMuPlugins)
            && $this->generateMuLoader($config)
            && $this->copyWebsiteDevContentToVipFolder($filesystem)
            && $this->generateAutoload($packages)
            && $this->syncronizeConfig($filesystem)
            && $this->gitMergeAndPush($filesystem, $config, $packages)
        ) {
            return 0;
        }

        return 1;
    }

    /**
     * Check presence of composer.lock.
     *
     * @param Event|null $event
     * @return bool
     */
    private function checkComposerLock(Event $event = null): bool
    {
        if (!file_exists($this->dirs->basePath() . '/composer.lock')) {
            $this->io->writeError('<error>VIP: "composer.lock" not found.</error>');
            if (!$event) {
                $this->io->writeError('<error>Please install or update via Composer first.</error>');
            }

            return false;
        }

        // ensure VID dirs are in place
        $this->dirs->createDirs();

        return true;
    }

    /**
     * Install VIP MU plugins via Git if required to do so.
     *
     * @param bool $shouldInstallVipMuPlugins
     * @return bool
     */
    private function installVipMuPlugins(bool $shouldInstallVipMuPlugins): bool
    {
        if ($shouldInstallVipMuPlugins) {
            (new VipGoMuDownloader($this->io, $this->dirs))->download();
        }

        return true;
    }

    /**
     * Symlink VIP folders to local WP installation content folder.
     *
     * @param bool $shouldInstallVipMuPlugins
     * @return bool
     */
    private function symlinkDirectories(bool $shouldInstallVipMuPlugins): bool
    {
        $contentDir = "/{$this->wpDownloaderConfig['target-dir']}/wp-content/";
        $contentDirPath = $this->dirs->basePath() . $contentDir;
        $this->io->write("<info>VIP: Symlinking content to {$contentDirPath}...</info>");

        $this->dirs->symlink($contentDirPath, $shouldInstallVipMuPlugins);

        return true;
    }

    /**
     * Generate a MU plugin that loads client MU plugins from subfolders and also load
     * production-ready Composer autoload.
     *
     * @param Config $config
     * @return bool
     */
    private function generateMuLoader(Config $config): bool
    {
        /** @var \Composer\Package\PackageInterface[] $package */
        $packages = $this->composer->getRepositoryManager()->getLocalRepository()->getPackages();
        $muGenerator = new MuPluginGenerator(
            $this->dirs,
            $config,
            new PluginFileFinder($this->installer)
        );

        $muGenerator->generate(...$packages);

        return true;
    }

    /**
     * Copy dev content (mu-plugins plugins, themes, languages) in website repo
     * to the "vip" folder that will be then used for deploy.
     *
     * @param Filesystem $filesystem
     * @return bool
     */
    private function copyWebsiteDevContentToVipFolder(Filesystem $filesystem): bool
    {
        $customPathCopier = new CustomPathCopier($filesystem, $this->extra);
        $customPathCopier->copy($this->dirs, $this->io);

        return true;
    }

    /**
     * Generate production-tailored autoloader.
     *
     * @param InstalledPackages $packages
     * @return bool
     * @throws \Exception
     */
    private function generateAutoload(InstalledPackages $packages): bool
    {
        $autoload = new AutoloadGenerator($packages, $this->dirs);
        $autoload->generate($this->composer, $this->io);

        return true;
    }

    /**
     * Syncronize config in "website" repo to "vip" folder.
     *
     * @param Filesystem $filesystem
     * @return bool
     */
    private function syncronizeConfig(Filesystem $filesystem): bool
    {
        $configSync = new ConfigSynchronizer($this->dirs, $this->io, $this->extra);
        $configSync->sync($filesystem, $this->wpDownloaderConfig['target-dir']);

        return true;
    }

    /**
     * Clone the remote Git repo, in a randomly-named folder inside "vip" folder, replace all the
     * files with current state and commit them.
     * Optionally, push back the changes to the remote and delete the randomly-named folder.
     *
     * @param Filesystem $filesystem
     * @param Config $config
     * @param InstalledPackages $packages
     * @return bool
     */
    private function gitMergeAndPush(
        Filesystem $filesystem,
        Config $config,
        InstalledPackages $packages
    ): bool {

        if (($this->flags & self::NO_GIT) === self::NO_GIT) {
            return true;
        }

        $remoteUrl = $this->commandConfig[Command::REMOTE_URL] ?? null;
        $remoteBranch = $this->commandConfig[Command::REMOTE_BRANCH] ?? null;

        $gitConfig = $this->extra[self::VIP_GIT_KEY] ?? [];
        $remoteUrl and $gitConfig[Plugin::VIP_GIT_URL_KEY] = $remoteUrl;
        $remoteBranch and $gitConfig[Plugin::VIP_GIT_BRANCH_KEY] = $remoteBranch;

        $git = new VipGit($this->io, $config, $this->dirs, $gitConfig);

        $push = ($this->flags & self::DO_PUSH) === self::DO_PUSH;
        $push ? $git->push($filesystem, $packages) : $git->sync($filesystem, $packages);

        return true;
    }

    /**
     * Download WP on installation.
     *
     * @param Event|null $event
     */
    public function wpInstall(Event $event = null)
    {
        $this->wpDownloader->install();
    }

    /**
     * Download WP on update.
     *
     * @param Event $event
     */
    public function wpUpdate(Event $event = null)
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
