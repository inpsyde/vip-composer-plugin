<?php

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
        return 'Symlink VIP folders';
    }

    /**
     * @param TaskConfig $taskConfig
     *
     * @return bool
     */
    public function enabled(TaskConfig $taskConfig): bool
    {
        return $taskConfig->isLocal();
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

        $map = [
            $uploadsPath => "{$contentDirPath}/{$uploadsDir}",
            $this->directories->vipMuPluginsDir() => "{$contentDirPath}/mu-plugins",
        ];
        foreach ($this->directories->toArray() as $dirPath) {
            $map[$dirPath] = "{$contentDirPath}/" . basename($dirPath);
        }

        $isWindows = Platform::isWindows();

        foreach ($map as $target => $link) {
            $this->filesystem->remove($link);
            $this->filesystem->ensureDirectoryExists($target);
            $link = $this->filesystem->normalizePath($link);

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
