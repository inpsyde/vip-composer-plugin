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

use Composer\Util\Filesystem;
use Inpsyde\VipComposer\Config;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\VipDirectories;

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
     * @var array
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
    public function __construct(Config $config, VipDirectories $directories, Filesystem $filesystem)
    {
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
        [, $source, $target, $pattern, $flags] = $this->pathInfoForKey($pathConfigKey);

        $sourcePaths = is_dir($source) ? (glob($pattern, $flags) ?: []) : [];
        $this->cleanupTarget($pathConfigKey, $target, $source);

        if (!$sourcePaths) {
            return 0;
        }

        $errors = 0;

        foreach ($sourcePaths as $sourcePath) {
            $targetPath = "{$target}/" . basename($sourcePath);

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
     * @return array
     *
     * phpcs:disable Inpsyde.CodeQuality.FunctionLength
     */
    private function pathInfoForKey(string $key): array
    {
        // phpcs:enable Inpsyde.CodeQuality.FunctionLength

        $sourceDir = $this->config[Config::DEV_PATHS_CONFIG_KEY][$key];
        $source = $this->filesystem->normalizePath($this->config->basePath() . "/{$sourceDir}");

        switch ($key) {
            case Config::DEV_PATHS_MUPLUGINS_DIR_KEY:
                $what = 'MU plugins';
                $target = $this->directories->muPluginsDir();
                $pattern = "{$source}/*.*";
                $flags = GLOB_NOSORT;
                break;
            case Config::DEV_PATHS_PLUGINS_DIR_KEY:
                $what = 'Plugins';
                $target = $this->directories->pluginsDir();
                $pattern = "{$source}/*";
                $flags = GLOB_NOSORT;
                break;
            case Config::DEV_PATHS_THEMES_DIR_KEY:
                $what = 'Themes';
                $target = $this->directories->themesDir();
                $pattern = "{$source}/*";
                $flags = GLOB_ONLYDIR | GLOB_NOSORT;
                break;
            case Config::DEV_PATHS_LANGUAGES_DIR_KEY:
                $what = 'Languages';
                $target = $this->directories->languagesDir();
                $pattern = "{$source}/*.{mo,po}";
                $flags = GLOB_BRACE | GLOB_NOSORT;
                break;
            case Config::DEV_PATHS_IMAGES_DIR_KEY:
                $what = 'Images';
                $target = $this->directories->imagesDir();
                $pattern = "{$source}/*.*";
                $flags = GLOB_NOSORT;
                break;
            case Config::DEV_PATHS_PHP_CONFIG_DIR_KEY:
                $what = 'PHP config files';
                $target = $this->directories->phpConfigDir();
                $pattern = "{$source}/*";
                $flags = GLOB_NOSORT;
                break;
            case Config::DEV_PATHS_YAML_CONFIG_DIR_KEY:
                $what = 'Yaml config files';
                $target = $this->directories->yamlConfigDir();
                $pattern = "{$source}/.*.yml";
                $flags = GLOB_NOSORT;
                break;
            case Config::DEV_PATHS_PRIVATE_DIR_KEY:
                $what = 'Private files';
                $target = $this->directories->privateDir();
                $pattern = "{$source}/*";
                $flags = GLOB_NOSORT;
                break;
            default:
                $what = '';
                $target = '';
                $pattern = '';
                $flags = null;
        }

        return [$what, $source, $target, $pattern, $flags];
    }

    /**
     * @param string $key
     * @param string $target
     * @param string $source
     * @return void
     */
    private function cleanupTarget(string $key, string $target, string $source): void
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

        /*
         * If dev path has sub-folder (e.g. `/themes/my-theme`), and that sub-folder exists under
         * `/vip` e.g. `/vip/themes/my-theme`, empty the target sub-folder before copying from
         * source to make sure files deleted on the source will not be there.
         */
        $sourceDirs = glob("{$source}/*", GLOB_ONLYDIR | GLOB_NOSORT);
        foreach ($sourceDirs as $sourceDir) {
            $sourceBasename = basename($sourceDir);
            if (
                $sourceBasename !== '.'
                && $sourceBasename !== '..'
                && is_dir("{$target}/{$sourceBasename}")
            ) {
                $this->filesystem->emptyDirectory("{$target}/{$sourceBasename}");
            }
        }

        $isMu = $key === Config::DEV_PATHS_MUPLUGINS_DIR_KEY;
        $isPrivate = $key === Config::DEV_PATHS_PRIVATE_DIR_KEY;
        foreach (glob("{$target}/*", GLOB_NOSORT) as $item) {
            /*
             * We need to preserve `/vip/private/deploy-id` and `/vip/private/deploy-ver` files,
             * but anything else in /vip/private` can be deleted before copying, as no Composer nor
             * any other source is expected to write there.
             */
            if ($isPrivate && is_dir($item)) {
                $this->filesystem->removeDirectory($item);
                continue;
            }

            $basename = ($item && is_file($item)) ? basename($item) : null;
            if (
                $basename
                && (!$isMu || ($basename !== '__loader.php'))
                && (!$isPrivate || !in_array($basename, ['deploy-id', 'deploy-ver'], true))
            ) {
                $this->filesystem->unlink($item);
            }
        }
    }
}
