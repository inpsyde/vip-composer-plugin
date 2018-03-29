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

use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Composer\Util\Platform;

class ConfigSynchronizer
{
    /**
     * @var Directories
     */
    private $dirs;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var array
     */
    private $extra;

    /**
     * @param Directories $dirs
     * @param IOInterface $io
     * @param array $extra
     */
    public function __construct(Directories $dirs, IOInterface $io, array $extra)
    {
        $this->dirs = $dirs;
        $this->io = $io;
        $this->extra = $extra;
    }

    /**
     * @param Filesystem $filesystem
     * @param string $wpDir
     */
    public function sync(Filesystem $filesystem, string $wpDir)
    {
        $configData = (array)($this->extra[Plugin::VIP_CONFIG_KEY] ?? []);
        $files = $this->syncFiles($configData, $filesystem);
        $this->updateWpConfig($configData, $wpDir, $files, $filesystem);
    }

    /**
     * @param array $configData
     * @param Filesystem $filesystem
     * @return array
     */
    private function syncFiles(array $configData, Filesystem $filesystem): array
    {
        $configDir = $configData[Plugin::VIP_CONFIG_DIR_KEY] ?? '';
        $configDir and $configDir = Platform::expandPath($configDir);
        if (!$configDir) {
            return [];
        }

        $configPath = $filesystem->isAbsolutePath($configDir)
            ? $configDir
            : $this->dirs->basePath() . "/{$configDir}";

        if (!is_dir($configPath)) {
            return [];
        }

        $this->io->write('<info>VIP: Syncing config files...</info>');
        $configs = glob("{$configPath}/*.php");

        $targetDir = $this->dirs->configDir() . '/';
        $filesystem->emptyDirectory($targetDir);

        $done = [];
        foreach ($configs as $source) {
            $target = $targetDir . basename($source);
            $success = $filesystem->copy($source, $target);
            $success and $done[] = $source;
            if ($this->io->isVerbose()) {
                $success
                    ? $this->io->write("    {$source} copied to {$target}.")
                    : $this->io->writeError("    Failed copy {$source} to {$target}.");
            }
        }

        return $done;
    }

    /**
     * @param array $config
     * @param string $wpDir
     * @param array $files
     * @param Filesystem $filesystem
     */
    private function updateWpConfig(
        array $config,
        string $wpDir,
        array $files,
        Filesystem $filesystem
    ) {

        $wpConfig = dirname($this->dirs->basePath() . "/{$wpDir}") . '/wp-config.php';
        if (!file_exists($wpConfig)) {
            return;
        }

        $configLoad = $config[Plugin::VIP_CONFIG_LOAD_KEY] ?? true;
        $files or $configLoad = false;

        $this->io->write("<info>VIP: Updating 'wp-config.php'...</info>");

        $wpComment = "/* That's all, stop editing! Happy blogging. */";
        $commentStart = '/* VIP Config START */';
        $commentEnd = '/* VIP Config END */';

        $currentContent = file_get_contents($wpConfig);
        $start = strpos($currentContent, $commentStart);
        $end = strpos($currentContent, $commentEnd);

        $contentPartsStart = $start
            ? explode($commentStart, $currentContent, 2)
            : explode($wpComment, $currentContent, 2);

        $contentPartsEnd = $end
            ? explode($commentEnd, $currentContent, 2)
            : explode($wpComment, $currentContent, 2);

        if (empty($contentPartsStart[0]) || empty($contentPartsEnd[1])) {
            $this->io->write("<comment>VIP: Can't update 'wp-config.php', it seems custom.</comment>");

            return;
        }

        $contentPartsStart[0] = str_replace($wpComment, '', $contentPartsStart[0]);
        $contentPartsEnd[1] = str_replace($wpComment, '', $contentPartsEnd[1]);

        $fileContent = rtrim($contentPartsStart[0]);

        if (!$configLoad) {
            $fileContent .= "\n\n{$wpComment}\n\n";
            $fileContent .= ltrim($contentPartsEnd[1]);
            $this->saveFile($wpConfig, $fileContent);

            return;
        }

        $fileContent .= "\n\n/* VIP Config START */\n";
        foreach ($files as $file) {
            $path = $filesystem->findShortestPath($this->dirs->basePath(), $file);
            $fileContent .= "require_once __DIR__ . '/{$path}';\n";
        }
        $fileContent .= "/* VIP Config END */\n\n";
        $fileContent .= "\n{$wpComment}\n\n";
        $fileContent .= ltrim($contentPartsEnd[1]);

        $this->saveFile($wpConfig, $fileContent);
    }

    /**
     * @param string $wpConfig
     * @param string $fileContent
     */
    private function saveFile(string $wpConfig, string $fileContent)
    {
        if (!file_put_contents($wpConfig, $fileContent)) {
            $this->io->writeError("<error>VIP: Failed writing 'wp-config.php'</error>");

            return;
        }

        $this->io->write("<info>VIP: 'wp-config.php' updated.</info>");
    }
}
