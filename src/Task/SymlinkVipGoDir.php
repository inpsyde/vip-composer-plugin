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
use Composer\Util\Platform;
use Inpsyde\VipComposer\Config;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\VipDirectories;

final class SymlinkVipGoDir implements Task
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
        return 'Symlink VIP folders';
    }

    /**
     * @param TaskConfig $taskConfig
     * @return bool
     */
    public function enabled(TaskConfig $taskConfig): bool
    {
        return $taskConfig->isLocal() || $taskConfig->forceCoreUpdate();
    }

    /**
     * @param Io $io
     * @param TaskConfig $taskConfig
     */
    public function run(Io $io, TaskConfig $taskConfig): void
    {
        $wpDir = $this->config->wpConfig()[Config::WP_LOCAL_DIR_KEY];
        $uploadsDir = $this->config->wpConfig()[Config::WP_LOCAL_UPLOADS_DIR_KEY];
        $contentDirPath = $this->config->basePath() . "/{$wpDir}/wp-content/";
        $uploadsPath = $this->filesystem->normalizePath($this->config->basePath() . '/uploads');

        $io->commentLine("Symlinking content to {$contentDirPath}...");

        $this->filesystem->ensureDirectoryExists($contentDirPath);
        $this->filesystem->emptyDirectory($contentDirPath);

        $map = [
            $this->directories->pluginsDir() => "{$contentDirPath}/plugins",
            $this->directories->themesDir() => "{$contentDirPath}/themes",
            $this->directories->languagesDir() => "{$contentDirPath}/languages",
            $this->directories->muPluginsDir() => "{$contentDirPath}/client-mu-plugins",
            $this->directories->vipMuPluginsDir() => "{$contentDirPath}/mu-plugins",
            $this->directories->imagesDir() => "{$contentDirPath}/images",
            $uploadsPath => "{$contentDirPath}/{$uploadsDir}",
        ];

        $isWindows = Platform::isWindows();

        foreach ($map as $target => $link) {
            if (is_dir($link)) {
                $this->filesystem->removeDirectory($link);
            }

            $this->filesystem->ensureDirectoryExists($target);
            $this->filesystem->normalizePath($link);

            if ($isWindows) {
                $this->filesystem->junction($target, $link);
                continue;
            }

            $this->filesystem->relativeSymlink($target, $link);
        }

        file_put_contents("{$contentDirPath}index.php", "<?php\n// Silence is golden.\n");

        $io->infoLine('Done!');
    }
}
