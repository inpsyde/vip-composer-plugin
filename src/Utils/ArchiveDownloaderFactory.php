<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Utils;

use Composer\Composer;
use Composer\Downloader\DownloadManager;
use Composer\Util\Filesystem;
use Composer\Util\ProcessExecutor;
use Composer\Util\Loop;
use Inpsyde\VipComposer\Io;

class ArchiveDownloaderFactory
{
    private const ARCHIVES = [
        ArchiveDownloader::ZIP,
        ArchiveDownloader::RAR,
        ArchiveDownloader::XZ,
        ArchiveDownloader::TAR,
    ];

    /** @var array<string, ArchiveDownloader> */
    private array $downloaders = [];
    private DownloadManager $downloadManager;
    private Loop $loop;

    /**
     * @param string $type
     * @return bool
     */
    public static function isValidType(string $type): bool
    {
        return in_array(strtolower($type), self::ARCHIVES, true);
    }

    /**
     * @param Io $io
     * @param Composer $composer
     * @param ProcessExecutor $executor
     * @param Filesystem $filesystem
     */
    public function __construct(
        private Io $io,
        Composer $composer,
        private Filesystem $filesystem
    ) {

        $this->downloadManager = $composer->getDownloadManager();
        $this->loop = $composer->getLoop();
    }

    /**
     * @param string $type
     * @return ArchiveDownloader
     */
    public function create(string $type): ArchiveDownloader
    {
        if (!empty($this->downloaders[$type])) {
            return $this->downloaders[$type];
        }

        if (!static::isValidType($type)) {
            throw new \Exception(sprintf("Invalid archive type: '%s'.", $type));
        }

        $downloader = $this->downloadManager->getDownloader($type);

        $this->downloaders[$type] = new ArchiveDownloader(
            $this->loop,
            $downloader,
            $this->io,
            $this->filesystem
        );

        return $this->downloaders[$type];
    }
}
