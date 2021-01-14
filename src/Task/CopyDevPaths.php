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
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Util\Filesystem;
use Inpsyde\VipComposer\Config;
use Inpsyde\VipComposer\Io;
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
     * @var Composer
     */
    private $composer;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var VipDirectories
     */
    private $directories;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @param Config $config
     * @param VipDirectories $directories
     * @param Filesystem $filesystem
     */
    public function __construct(
        Composer $composer,
        Config $config,
        VipDirectories $directories,
        Filesystem $filesystem
    ) {

        $this->composer = $composer;
        $this->config = $config;
        $this->directories = $directories;
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
            $failures += $this->copyCustomPath($dirName, $io);
        }

        $failures += $this->copyMuPluginsPackages($io);

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
    private function copyCustomPath(string $pathConfigKey, Io $io): int
    {
        [, $source, $target, $sourcePaths] = $this->pathInfoForKey($pathConfigKey);

        if (is_dir($target)) {
            $this->cleanupTarget($pathConfigKey, $target);
            $sourcePaths and $this->cleanupSource($target, $source);
        }

        if (!$sourcePaths) {
            return 0;
        }

        return $this->copy($sourcePaths, $target, $io);
    }

    /**
     * @param Io $io
     *
     * @return int
     */
    private function copyMuPluginsPackages(Io $io): int
    {
        $rootPackage = $this->composer->getPackage();
        $repositoryManager = $this->composer->getRepositoryManager();
        $target = $this->directories->muPluginsDir();

        $errors = 0;
        foreach ($rootPackage->getRequires() as $link) {
            /** @var ConstraintInterface $constraint */
            $constraint = $link->getConstraint();
            if (!$constraint instanceof Constraint) {
                continue;
            }
            $package = $repositoryManager->findPackage($link->getTarget(), $constraint);
            if ($package && $package->getType() === Config::PACKAGE_TYPE_MULTIPLE_MU_PLUGINS) {
                $vendorDir = $this->composer->getConfig()->get('vendor-dir');
                $packagePath = $vendorDir . '/' . $package->getPrettyName();
                $packageFiles = Finder::create()->in($packagePath)->files()->name('*.php');

                $errors += $this->copy($packageFiles, $target, $io, true);
            }
        }

        return $errors;
    }

    /**
     * @param Finder $sourcePaths
     * @param string $target
     * @param Io $io
     * @param bool $singleFiles If to copy the whole folder or single files inside it
     *
     * @return int The number of errors encountered. 0 if all the files have been copied correctly.
     */
    private function copy(Finder $sourcePaths, string $target, Io $io, bool $singleFiles = false): int
    {
        $errors = 0;
        /** @var SplFileInfo $sourcePathInfo */
        foreach ($sourcePaths as $sourcePathInfo) {
            $sourcePath = $sourcePathInfo->getPathname();
            $finalTarget = $singleFiles ? $sourcePathInfo->getFilename() : $sourcePathInfo->getBasename();
            $targetPath = "{$target}/{$finalTarget}";

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
                $finder and $finder->files()->ignoreDotFiles(true);
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
                $finder and $finder->files()->ignoreDotFiles(true)->filter(
                    static function (SplFileInfo $info): bool {
                        return in_array(strtolower($info->getExtension()), ['po', 'mo'], true);
                    }
                );
                break;
            case Config::DEV_PATHS_IMAGES_DIR_KEY:
                $what = 'Images';
                $target = $this->directories->imagesDir();
                $finder and $finder->files()->ignoreDotFiles(true);
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
}
