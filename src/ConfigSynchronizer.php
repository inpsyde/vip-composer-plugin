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
    const DEFAULT_CONFIG = [
        Plugin::VIP_CONFIG_DIR_KEY => 'config',
        Plugin::VIP_CONFIG_LOAD_KEY => true,
    ];

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
    private $config;

    /**
     * @param Directories $dirs
     * @param IOInterface $io
     * @param array $extra
     */
    public function __construct(Directories $dirs, IOInterface $io, array $extra)
    {
        $this->dirs = $dirs;
        $this->io = $io;
        $config = (array)($extra[Plugin::VIP_CONFIG_KEY] ?? []);
        $dir = $config[Plugin::VIP_CONFIG_DIR_KEY] ?? '';
        if ($dir) {
            $config[Plugin::VIP_CONFIG_DIR_KEY] = Platform::expandPath($dir);
        }
        $this->config = array_merge(self::DEFAULT_CONFIG, $config);
    }

    /**
     * @param Filesystem $filesystem
     * @param string $wpDir
     */
    public function sync(Filesystem $filesystem, string $wpDir)
    {
        $vipConfig = $this->syncFiles($filesystem);
        $this->updateWpConfig($wpDir, $vipConfig, $filesystem);
    }

    /**
     * @param Filesystem $filesystem
     * @return string
     */
    private function syncFiles(Filesystem $filesystem): string
    {
        $configDir = $this->config[Plugin::VIP_CONFIG_DIR_KEY];
        if (!$configDir) {
            return '';
        }

        $sourcePath = $filesystem->isAbsolutePath($configDir)
            ? $configDir
            : $this->dirs->basePath() . "/{$configDir}";

        if (!is_dir($sourcePath)) {
            return '';
        }

        $this->io->write('<info>VIP: Syncing config files...</info>');
        $configs = glob("{$sourcePath}/*.php");

        $targetDir = $this->dirs->configDir() . '/';
        $filesystem->emptyDirectory($targetDir);

        $vipConfig = '';
        foreach ($configs as $source) {
            $target = $targetDir . basename($source);
            $success = $filesystem->copy($source, $target);
            ($success && basename($target) === 'vip-config.php') and $vipConfig = $target;
            if ($this->io->isVerbose()) {
                $success
                    ? $this->io->write("    {$source} copied to {$target}.")
                    : $this->io->writeError("    Failed copy {$source} to {$target}.");
            }
        }

        return $vipConfig;
    }

    /**
     * @param string $wpDir
     * @param string $vipConfig
     * @param Filesystem $filesystem
     */
    private function updateWpConfig(
        string $wpDir,
        string $vipConfig,
        Filesystem $filesystem
    ) {

        $wpConfig = dirname($this->dirs->basePath() . "/{$wpDir}") . '/wp-config.php';
        if (!file_exists($wpConfig)) {
            return;
        }

        $configLoad = $this->config[Plugin::VIP_CONFIG_LOAD_KEY];
        $configLoad or $vipConfig = '';

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

        $fileContent .= "\n\n/* VIP Config START */\n";

        $muPath = Directories::VIP_GO_MUPLUGINS_DIR;
        $fileContent .= <<<PHP
if (is_dir(__DIR__ . '/{$muPath}')) {
    define( 'WPMU_PLUGIN_DIR',__DIR__ . '/vip-go-mu-plugins' );
}
PHP;
        if ($vipConfig) {
            $path = $filesystem->findShortestPath($this->dirs->basePath(), $vipConfig);
            $fileContent .= "\nrequire_once __DIR__ . '/{$path}';";
        }

        $fileContent .= "\n/* VIP Config END */\n\n";
        $fileContent .= "\n{$wpComment}\n\n";
        $fileContent .= ltrim($contentPartsEnd[1]);

        $this->saveFile($wpConfig, $this->ensureDebug($fileContent));
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

    /**
     * @param string $fileContent
     * @return string
     */
    private function ensureDebug(string $fileContent): string
    {
        $lines = explode("\n", $fileContent);
        $parsed = '';
        foreach ($lines as $line) {
            $line = rtrim($line);
            if (strpos($line, "define('WP_DEBUG'") === 0) {
                $line = "define('WP_DEBUG', true);";
            }

            $parsed .= "{$line}\n";
        }

        return $parsed;
    }
}
