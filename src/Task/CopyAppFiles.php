<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Task;

use Composer\Util\Filesystem;
use Inpsyde\VipComposer\Config;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\VipDirectories;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class CopyAppFiles implements Task
{
    private Finder $muPluginsSourcePath;
    private Finder $vipConfigSourcePath;

    /**
     * @param Config $config
     * @param VipDirectories $directories
     * @param Filesystem $filesystem
     */
    public function __construct(
        Config $config,
        private VipDirectories $directories,
        private Filesystem $filesystem
    ) {

        $sourcePath = $filesystem->normalizePath($config->pluginPath()) . '/app';
        $this->muPluginsSourcePath = Finder::create()
            ->in("{$sourcePath}/mu-plugins")
            ->depth('== 0')
            ->name('*.php');
        $this->vipConfigSourcePath = Finder::create()
            ->in("{$sourcePath}/vip-config")
            ->depth('== 0')
            ->name('*.php');
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return 'Copy application files';
    }

    /**
     * @param TaskConfig $taskConfig
     * @return bool
     */
    public function enabled(TaskConfig $taskConfig): bool
    {
        return true;
    }

    /**
     * @param Io $io
     * @param TaskConfig $taskConfig
     */
    public function run(Io $io, TaskConfig $taskConfig): void
    {
        if (!$io->composerIo()->isVerbose()) {
            $io->commentLine('Copying application files to VIP folder...');
        }

        $vipConfigTarget = $this->directories->phpConfigDir();
        if ($this->copySources($this->vipConfigSourcePath, $vipConfigTarget, $io) > 0) {
            throw new \Error('Failed copying pre-defined VIP configuration files and helpers.');
        }

        $muPluginsTarget = $this->directories->muPluginsDir();
        if ($this->copySources($this->muPluginsSourcePath, $muPluginsTarget, $io) > 0) {
            throw new \Error('Failed copying pre-defined MU plugins.');
        }

        if (!$io->composerIo()->isVerbose()) {
            $io->infoLine('Application files copied to VIP folder.');
        }
    }

    /**
     * @return list<string>
     */
    public function vipConfigNames(): array
    {
        return $this->sourceNames($this->vipConfigSourcePath);
    }

    /**
     * @return list<string>
     */
    public function muPluginNames(): array
    {
        return $this->sourceNames($this->muPluginsSourcePath);
    }

    /**
     * @param Finder $sourcePaths
     * @return list<string>
     */
    private function sourceNames(Finder $sourcePaths): array
    {
        $names = [];
        foreach ($sourcePaths as $sourcePathInfo) {
            $names[] = $sourcePathInfo->getBasename();
        }

        return $names;
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

            if (file_exists($targetPath)) {
                $io->verboseInfoLine("File '{$targetPath}' exists, replacing...");
                $this->filesystem->unlink($targetPath);
            }

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
