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

final class UpdateLocalWpConfigFile implements Task
{
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
        return 'Update local wp-config.php';
    }

    /**
     * @param TaskConfig $taskConfig
     * @return bool
     */
    public function enabled(TaskConfig $taskConfig): bool
    {
        return $taskConfig->isLocal() || $taskConfig->forceVipMuPlugins();
    }

    /**
     * @param Io $io
     * @param TaskConfig $taskConfig
     * @return void
     */
    public function run(Io $io, TaskConfig $taskConfig): void
    {
        $wpDir = $this->config->wpConfig()[Config::WP_LOCAL_DIR_KEY];
        $wpConfigPath = dirname($this->config->basePath() . "/{$wpDir}") . '/wp-config.php';
        if (!file_exists($wpConfigPath)) {
            return;
        }

        $io->commentLine("Updating 'wp-config.php'...");

        $currentContent = file_get_contents($wpConfigPath);

        $wpCommentRegex = "~/\* That's all, stop editing!(?:[^\*]+)\*/~";
        preg_match($wpCommentRegex, $currentContent, $matches);
        $wpCommentText = $matches ? $matches[0] : '';

        $commentStart = '/* VIP Config START */';
        $commentEnd = '/* VIP Config END */';

        $start = strpos($currentContent, $commentStart);
        $end = strpos($currentContent, $commentEnd);

        $contentPartsStart = $start
            ? explode($commentStart, $currentContent, 2)
            : (preg_split($wpCommentRegex, $currentContent, 2) ?: []);

        $contentPartsEnd = $end
            ? explode($commentEnd, $currentContent, 2)
            : (preg_split($wpCommentRegex, $currentContent, 2) ?: []);

        if (empty($contentPartsStart[0]) || empty($contentPartsEnd[1])) {
            $io->errorLine("Can't update 'wp-config.php', it seems custom.");

            return;
        }

        $contentPartsStart[0] = str_replace($wpCommentText, '', $contentPartsStart[0]);
        $contentPartsEnd[1] = str_replace($wpCommentText, '', $contentPartsEnd[1]);

        $fileContent = rtrim($contentPartsStart[0]);
        $fileContent .= "\n\n/* VIP Config START */\n";

        $muPath = $this->directories->vipMuPluginsDir();

        $fileContent .= <<<PHP
if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
    define('WPMU_PLUGIN_DIR', '{$muPath}');
}
PHP;
        $vipConfigMainFile = $this->directories->phpConfigDir() . '/vip-config.php';

        if (file_exists($vipConfigMainFile)) {
            $vipConfigMainFileRelPath = $this->filesystem->findShortestPath(
                $this->config->basePath(),
                $vipConfigMainFile
            );
            $fileContent .= "\nrequire_once __DIR__ . '/{$vipConfigMainFileRelPath}';";
        }

        $fileContent .= "\n/* VIP Config END */\n\n";
        $fileContent .= "\n{$wpCommentText}\n\n";
        $fileContent .= ltrim($contentPartsEnd[1]);

        $this->saveFile($wpConfigPath, $this->ensureDebug($fileContent), $io);
    }

    /**
     * @param string $wpConfigPath
     * @param string $fileContent
     * @param Io $io
     */
    private function saveFile(string $wpConfigPath, string $fileContent, Io $io): void
    {
        if (!file_put_contents($wpConfigPath, $fileContent)) {
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
}
