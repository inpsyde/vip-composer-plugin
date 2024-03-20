<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Task;

use Composer\Util\Filesystem;
use Inpsyde\VipComposer\Config;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\VipDirectories;

final class UpdateLocalWpConfigFile implements Task
{
    /**
     * @param Config $config
     * @param VipDirectories $directories
     * @param Filesystem $filesystem
     */
    public function __construct(
        private Config $config,
        private VipDirectories $directories,
        private Filesystem $filesystem
    ) {
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return 'Update local wp-config.php';
    }

    /**
     * @param TaskConfig $taskConfig
     * @return bool
     */
    public function enabled(TaskConfig $taskConfig): bool
    {
        return $taskConfig->isLocal()
            || $taskConfig->forceVipMuPlugins()
            || $taskConfig->forceCoreUpdate();
    }

    /**
     * @param Io $io
     * @param TaskConfig $taskConfig
     * @return void
     */
    public function run(Io $io, TaskConfig $taskConfig): void
    {
        $wpDir = (string) $this->config->wpConfig()[Config::WP_LOCAL_DIR_KEY];
        $wpConfigPath = dirname($this->config->basePath() . "/{$wpDir}") . '/wp-config.php';
        if (!file_exists($wpConfigPath)) {
            return;
        }

        $io->commentLine("Updating 'wp-config.php'...");

        [$contentStart, $contentEnd] = $this->parseCurrentContent($wpConfigPath, $io);
        if ($contentStart === null) {
            return;
        }

        $content = $contentStart;

        $muPath = $this->directories->vipMuPluginsDir();
        $content .= <<<PHP
        if (!defined('WP_INSTALLING') || !WP_INSTALLING && is_dir('{$muPath}')) {
            define('WPMU_PLUGIN_DIR', '{$muPath}');
        }
        if (!defined('SUNRISE')) {
            define('SUNRISE', true);
        }
        PHP;

        $requiresPath = $this->directories->vipMuPluginsDir() . '/000-pre-vip-config/requires.php';
        if (file_exists($requiresPath)) {
            $requiresRelPath = $this->filesystem->findShortestPath(
                $this->config->basePath(),
                $requiresPath
            );
            $content .= "\nrequire_once __DIR__ . '/{$requiresRelPath}';";
        }

        $vipConfigMainFile = $this->directories->phpConfigDir() . '/vip-config.php';
        if (file_exists($vipConfigMainFile)) {
            $vipConfigMainFileRelPath = $this->filesystem->findShortestPath(
                $this->config->basePath(),
                $vipConfigMainFile
            );
            $content .= "\nrequire_once __DIR__ . '/{$vipConfigMainFileRelPath}';";
        }

        $content .= $contentEnd;

        $this->saveFile($wpConfigPath, $this->ensureDebug($content), $io);

        $this->copyClientSunrise($wpDir, $io);
    }

    /**
     * @param string $wpConfigPath
     * @param Io $io
     * @return list{non-falsy-string|null, string}
     */
    private function parseCurrentContent(string $wpConfigPath, Io $io): array
    {
        $currentContent = (string) file_get_contents($wpConfigPath);

        $wpCommentRegex = "~/\* That's all, stop editing!(?:[^\*]+)\*/~";
        preg_match($wpCommentRegex, $currentContent, $matches);
        $wpCommentText = $matches ? $matches[0] : '';

        $commentStart = '/* VIP Config START */';
        $commentEnd = '/* VIP Config END */';

        $start = strpos($currentContent, $commentStart);
        $end = strpos($currentContent, $commentEnd);

        $contentPartsStart = ($start !== false)
            ? explode($commentStart, $currentContent, 2)
            : (preg_split($wpCommentRegex, $currentContent, 2) ?: []);

        $contentPartsEnd = ($end !== false)
            ? explode($commentEnd, $currentContent, 2)
            : (preg_split($wpCommentRegex, $currentContent, 2) ?: []);

        if (!isset($contentPartsStart[0]) || !isset($contentPartsEnd[1])) {
            $io->errorLine("Can't update 'wp-config.php', it seems custom.");

            return [null, ''];
        }

        $contentPartsStart[0] = str_replace($wpCommentText, '', $contentPartsStart[0]);
        $contentPartsEnd[1] = str_replace($wpCommentText, '', $contentPartsEnd[1]);

        $start = rtrim($contentPartsStart[0]);
        $start .= "\n\n/* VIP Config START */\n";

        $end = "\n/* VIP Config END */\n\n";
        $end .= "\n{$wpCommentText}\n\n";
        $end .= ltrim($contentPartsEnd[1]);

        return [$start, $end];
    }

    /**
     * @param string $wpConfigPath
     * @param string $fileContent
     * @param Io $io
     */
    private function saveFile(string $wpConfigPath, string $fileContent, Io $io): void
    {
        if (file_put_contents($wpConfigPath, $fileContent) === false) {
            $io->errorLine("Failed writing 'wp-config.php'");

            return;
        }

        $io->infoLine("'wp-config.php' updated.");
    }

    /**
     * @param string $wpConfigFileContent
     * @return string
     */
    private function ensureDebug(string $wpConfigFileContent): string
    {
        $lines = explode("\n", $wpConfigFileContent);
        $parsed = '';
        foreach ($lines as $line) {
            $line = rtrim($line);
            if (preg_match("~^(\s*)?define\(\s*'WP_DEBUG',\s*[^\)]+\)\s*;~", $line, $match)) {
                $line = ($match[1] ?? '') . "define('WP_DEBUG', true);";
            }

            $parsed .= "{$line}\n";
        }

        return rtrim($parsed) . "\n";
    }

    /**
     * @param string $abspath
     * @param Io $io
     * @return void
     */
    private function copyClientSunrise(string $abspath, Io $io): void
    {
        $io->infoLine('Copying client-sunrise.php to ABSPATH');
        $sourcePath = $this->config->pluginPath() . '/app/vip-config/client-sunrise.php';
        $targetDir = "{$abspath}/vip-config";
        $targetPath = "{$targetDir}/client-sunrise.php";

        $this->filesystem->ensureDirectoryExists($targetDir);

        if (file_exists($targetPath)) {
            $io->verboseCommentLine("{$targetPath} exists, replacing...");
            $this->filesystem->unlink($targetPath);
        }

        $this->filesystem->copy($sourcePath, $targetPath);
    }
}
