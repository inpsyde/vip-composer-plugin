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

use Composer\Installer\InstallationManager;
use Composer\Util\Filesystem;
use Inpsyde\VipComposer\Config;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\Utils\PackageFinder;
use Inpsyde\VipComposer\VipDirectories;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class CopyDevPaths implements Task
{
    public const PATHS = [
        Config::DEV_PATHS_MUPLUGINS_DIR_KEY,
        Config::DEV_PATHS_PLUGINS_DIR_KEY,
        Config::DEV_PATHS_THEMES_DIR_KEY,
        Config::DEV_PATHS_LANGUAGES_DIR_KEY,
        Config::DEV_PATHS_IMAGES_DIR_KEY,
        Config::DEV_PATHS_PHP_CONFIG_DIR_KEY,
        Config::DEV_PATHS_YAML_CONFIG_DIR_KEY,
        Config::DEV_PATHS_PRIVATE_DIR_KEY,
    ];

    /**
     * @var Config
     */
    private $config;

    /**
     * @var VipDirectories
     */
    private $directories;

    /**
     * @var PackageFinder
     */
    private $packageFinder;

    /**
     * @var InstallationManager
     */
    private $installationManager;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @param Config $config
     * @param VipDirectories $directories
     * @param PackageFinder $packageFinder
     * @param InstallationManager $installationManager
     * @param Filesystem $filesystem
     */
    public function __construct(
        Config $config,
        VipDirectories $directories,
        PackageFinder $packageFinder,
        InstallationManager $installationManager,
        Filesystem $filesystem
    ) {

        $this->config = $config;
        $this->directories = $directories;
        $this->packageFinder = $packageFinder;
        $this->installationManager = $installationManager;
        $this->filesystem = $filesystem;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return 'Copy development folders';
    }

    /**
     * @param TaskConfig $taskConfig
     * @return bool
     */
    public function enabled(TaskConfig $taskConfig): bool
    {
        return $taskConfig->isLocal() || $taskConfig->isDeploy() || $taskConfig->syncDevPaths();
    }

    /**
     * @param Io $io
     * @param TaskConfig $taskConfig
     */
    public function run(Io $io, TaskConfig $taskConfig): void
    {
        if (!$io->composerIo()->isVerbose()) {
            $io->commentLine('Copying dev paths to VIP folder...');
        }

        $failures = 0;
        foreach (self::PATHS as $dirName) {
            $failures += $this->copyDevPath($dirName, $io);
        }

        $failures += $this->copyMultiMuPlugins($io);

        if ($failures && !$taskConfig->isOnlyLocal()) {
            throw new \RuntimeException("{$failures} occurred copying dev paths to VIP folder.");
        }

        $pluginIndex = $this->directories->pluginsDir() . '/index.php';
        if (!file_exists($pluginIndex)) {
            file_put_contents($pluginIndex, "<?php\n// Silence is golden.\n");
        }

        if (!$io->composerIo()->isVerbose()) {
            $io->infoLine('Dev paths copied to VIP folder.');
        }
    }

    /**
     * @param string $pathConfigKey
     * @param Io $io
     * @return int
     */
    private function copyDevPath(string $pathConfigKey, Io $io): int
    {
        [$what, $source, $target, $sourcePaths] = $this->pathInfoForKey($pathConfigKey);

        if (is_dir($target)) {
            $this->cleanupTarget($pathConfigKey, $target);
            $sourcePaths and $this->cleanupSource($target, $source);
        }

        if (!$sourcePaths) {
            return 0;
        }

        $io->verboseCommentLine("Copying {$what} in dev path to VIP target folder...");

        return $this->copySources($sourcePaths, $target, $io);
    }

    /**
     * @param string $key
     * @return array{string, string, string, Finder|null}
     *
     * phpcs:disable Inpsyde.CodeQuality.FunctionLength
     * phpcs:disable Inpsyde.CodeQuality.NestingLevel
     * phpcs:disable Generic.Metrics.CyclomaticComplexity
     */
    private function pathInfoForKey(string $key): array
    {
        // phpcs:enable Inpsyde.CodeQuality.FunctionLength
        // phpcs:enable Inpsyde.CodeQuality.NestingLevel
        // phpcs:enable Generic.Metrics.CyclomaticComplexity

        /** @psalm-suppress MixedArrayAccess */
        $sourceDir = (string)$this->config[Config::DEV_PATHS_CONFIG_KEY][$key];
        $source = $this->filesystem->normalizePath($this->config->basePath() . "/{$sourceDir}");

        $finder = null;
        if (is_dir($source)) {
            $finder = Finder::create()
                ->in($source)
                ->depth('== 0')
                ->ignoreUnreadableDirs()
                ->ignoreVCS(true);
        }

        switch ($key) {
            case Config::DEV_PATHS_MUPLUGINS_DIR_KEY:
                $what = 'MU plugins';
                $target = $this->directories->muPluginsDir();
                $finder and $finder->ignoreDotFiles(true);
                break;
            case Config::DEV_PATHS_PLUGINS_DIR_KEY:
                $what = 'Plugins';
                $target = $this->directories->pluginsDir();
                $finder and $finder->ignoreDotFiles(true);
                break;
            case Config::DEV_PATHS_THEMES_DIR_KEY:
                $what = 'Themes';
                $target = $this->directories->themesDir();
                $finder and $finder->directories()->ignoreDotFiles(true);
                break;
            case Config::DEV_PATHS_LANGUAGES_DIR_KEY:
                $what = 'Languages';
                $target = $this->directories->languagesDir();
                $finder and $finder->files()->ignoreDotFiles(true);
                break;
            case Config::DEV_PATHS_IMAGES_DIR_KEY:
                $what = 'Images';
                $target = $this->directories->imagesDir();
                $finder and $finder->ignoreDotFiles(true);
                break;
            case Config::DEV_PATHS_PHP_CONFIG_DIR_KEY:
                $what = 'PHP config files';
                $target = $this->directories->phpConfigDir();
                $finder and $finder->ignoreDotFiles(true);
                break;
            case Config::DEV_PATHS_YAML_CONFIG_DIR_KEY:
                $what = 'Yaml config files';
                $target = $this->directories->yamlConfigDir();
                $finder and $finder->files()->ignoreDotFiles(false)->filter(
                    static function (SplFileInfo $info): bool {
                        return (bool)preg_match('~\.[^\.]+\.yml$~i', $info->getFilename());
                    }
                );
                break;
            case Config::DEV_PATHS_PRIVATE_DIR_KEY:
                $what = 'Private files';
                $target = $this->directories->privateDir();
                $finder and $finder->ignoreDotFiles(false);
                break;
            default:
                $what = '';
                $target = '';
                $finder = null;
        }

        return [$what, $source, $target, $finder];
    }

    /**
     * @param Io $io
     * @return int
     */
    private function copyMultiMuPlugins(Io $io): int
    {
        $multiMuPackages = $this->packageFinder->findByType(Config::PACKAGE_TYPE_MULTI_MU_PLUGINS);
        $multiMuTarget = $this->directories->muPluginsDir();

        $errors = 0;
        foreach ($multiMuPackages as $package) {
            $path = $this->installationManager->getInstallPath($package);
            if (!is_dir($path)) {
                continue;
            }

            $name = $package->getPrettyName();
            $io->verboseCommentLine("Copying {$name} MU plugin files to VIP target folder...");
            $source = Finder::create()->in($path)->depth('== 0')->name('*.php');
            $errors += $this->copySources($source, $multiMuTarget, $io);
        }

        return $errors;
    }

    /**
     * @param string $key
     * @param string $target
     * @return void
     */
    private function cleanupTarget(string $key, string $target): void
    {
        // Do nothing for languages: emptying the folder would conflict with translation downloader.
        if ($key === Config::DEV_PATHS_LANGUAGES_DIR_KEY) {
            return;
        }

        /* These directories are only filled from root, we can safely empty them before copying. */
        switch ($key) {
            case Config::DEV_PATHS_IMAGES_DIR_KEY:
            case Config::DEV_PATHS_PHP_CONFIG_DIR_KEY:
            case Config::DEV_PATHS_YAML_CONFIG_DIR_KEY:
                $this->filesystem->emptyDirectory($target);
                return;
        }

        $isMu = $key === Config::DEV_PATHS_MUPLUGINS_DIR_KEY;
        $isPrivate = $key === Config::DEV_PATHS_PRIVATE_DIR_KEY;

        $targets = Finder::create()->in($target)->ignoreUnreadableDirs()->depth('== 0');

        /** @var SplFileInfo $item */
        foreach ($targets as $item) {
            /*
             * We need to preserve `/vip/private/deploy-id` and `/vip/private/deploy-ver` files,
             * but anything else in /vip/private` can be deleted before copying, as no Composer nor
             * any other source is expected to write there.
             */
            if ($isPrivate && $item->isDir()) {
                $this->filesystem->removeDirectory($item->getPathname());
                continue;
            }

            $basename = $item->isFile() ? $item->getBasename() : null;
            if (
                $basename
                && (!$isMu || ($basename !== '__loader.php'))
                && (!$isPrivate || !in_array($basename, ['deploy-id', 'deploy-ver'], true))
            ) {
                $this->filesystem->unlink($item->getPathname());
            }
        }
    }

    /**
     * @param string $target
     * @param string $source
     * @return void
     */
    private function cleanupSource(string $target, string $source): void
    {
        /*
         * If dev path has sub-folder (e.g. `/themes/my-theme`), and that sub-folder exists under
         * `/vip` e.g. `/vip/themes/my-theme`, empty the target sub-folder before copying from
         * source to make sure files deleted on the source will not be there.
         */
        $sourceDirs = Finder::create()
            ->in($source)
            ->directories()
            ->depth('== 0')
            ->ignoreUnreadableDirs();

        /** @var SplFileInfo $sourceDir */
        foreach ($sourceDirs as $sourceDir) {
            $sourceBasename = $sourceDir->getBasename();
            if (is_dir("{$target}/{$sourceBasename}")) {
                $this->filesystem->emptyDirectory("{$target}/{$sourceBasename}");
            }
        }
    }

    /**
     * @param Finder $sourcePaths
     * @param string $target
     * @param Io $io
     * @return int
     */
    private function copySources(Finder $sourcePaths, string $target, Io $io): int
    {
        $errors = 0;

        /** @var SplFileInfo $sourcePathInfo */
        foreach ($sourcePaths as $sourcePathInfo) {
            $sourcePath = $sourcePathInfo->getPathname();
            $targetPath = "{$target}/" . $sourcePathInfo->getBasename();

            if ($this->filesystem->copy($sourcePath, $targetPath)) {
                $from = "<comment>'{$sourcePath}'</comment>";
                $to = "<comment>'{$targetPath}'</comment>";
                $io->verboseInfoLine("- copied</info> {$from} <info>to</info> {$to}<info>");
                continue;
            }

            $errors++;
            $io->errorLine("- failed copying '{$sourcePath}' to '{$targetPath}'.");
        }

        return $errors;
    }
}
