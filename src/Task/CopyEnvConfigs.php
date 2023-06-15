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

use Composer\Installer\InstallationManager;
use Composer\Util\Filesystem;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\Utils\PackageFinder;
use Inpsyde\VipComposer\VipDirectories;

final class CopyEnvConfigs implements Task
{
    public const COMPOSER_TYPE = 'vip-composer-plugin-env-config';

    /**
     * @var VipDirectories
     */
    private $directories;

    /**
     * @var PackageFinder
     */
    private $packageFinder;

    /**
     * @var InstallationManager
     */
    private $installationManager;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var \Composer\Package\PackageInterface[]|null
     */
    private $packages = null;

    /**
     * @param VipDirectories $directories
     * @param PackageFinder $packageFinder
     * @param InstallationManager $installationManager
     * @param Filesystem $filesystem
     */
    public function __construct(
        VipDirectories $directories,
        PackageFinder $packageFinder,
        InstallationManager $installationManager,
        Filesystem $filesystem
    ) {

        $this->directories = $directories;
        $this->packageFinder = $packageFinder;
        $this->installationManager = $installationManager;
        $this->filesystem = $filesystem;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return 'Copy external environment configuration ';
    }

    /**
     * @param TaskConfig $taskConfig
     * @return bool
     */
    public function enabled(TaskConfig $taskConfig): bool
    {
        return $this->findPackages() !== [];
    }

    /**
     * @param Io $io
     * @param TaskConfig $taskConfig
     */
    public function run(Io $io, TaskConfig $taskConfig): void
    {
        $packages = $this->findPackagesToProcess($io);

        [$targetDir, $sourceFiles] = $this->determinePaths($packages);

        $copied = [];
        $all = [];
        foreach ($sourceFiles as $sourceFile) {
            [$all, $copied] = $this->copyFile($sourceFile, $targetDir, $io, $all, $copied);
        }

        $this->finalMessage($io, $all, $copied, $packages);
    }

    /**
     * @return array
     */
    private function findPackages(): array
    {
        if ($this->packages === null) {
            $this->packages = $this->packageFinder->findByType(self::COMPOSER_TYPE);
        }

        return $this->packages;
    }

    /**
     * @param Io $io
     * @return \Composer\Package\PackageInterface[]
     */
    private function findPackagesToProcess(Io $io): array
    {
        $packages = $this->findPackages();
        $packageCount = count($packages);
        if ($packageCount < 1) {
            return [];
        }

        $io->commentLine(
            sprintf(
                'Found %d environment configuration package%s.',
                $packageCount,
                ($packageCount === 1) ? '' : 's'
            )
        );

        return $packages;
    }

    /**
     * @param \Composer\Package\PackageInterface[] $packages
     * @return array{
     *     non-empty-string,
     *     array<'all'|'development'|'staging'|'production', string>
     * }
     */
    private function determinePaths(array $packages): array
    {
        $targetDir = $this->directories->phpConfigDir() . '/env';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true)) {
            throw new \RuntimeException("Could not create target folder '{$targetDir}'.");
        }

        $sourcesDirs = [];
        foreach ($packages as $package) {
            $sourcesDirs[] = $this->installationManager->getInstallPath($package);
        }

        $sourceFiles = [];
        foreach ($sourcesDirs as $sourceDir) {
            if (!is_dir($sourceDir)) {
                continue;
            }
            foreach (['all', 'development', 'staging', 'production'] as $env) {
                $sourceFiles[$env] = $this->filesystem->normalizePath("{$sourceDir}/{$env}.php");
            }
        }

        return [$targetDir, $sourceFiles];
    }

    /**
     * @param string $sourceFile
     * @param string $targetDir
     * @param Io $io
     * @param list<string> $all
     * @param list<string> $copied
     * @return array{list<string>, list<string>}
     */
    private function copyFile(
        string $sourceFile,
        string $targetDir,
        Io $io,
        array $all,
        array $copied
    ): array {

        if (!file_exists($sourceFile)) {
            return [$all, $copied];
        }

        $basename = basename($sourceFile);
        $targetFile = "{$targetDir}/{$basename}";
        $all[] = $basename;

        if (file_exists($targetFile)) {
            $io->verboseInfoLine("File 'env/{$basename}' exists, replacing...");
            $this->filesystem->unlink($targetFile);
        }

        if ($this->filesystem->copy($sourceFile, $targetFile)) {
            $copied[] = $basename;
        }

        return [$all, $copied];
    }

    /**
     * @param Io $io
     * @param list<string> $all
     * @param list<string> $copied
     * @param \Composer\Package\PackageInterface[] $packages
     * @return void
     */
    private function finalMessage(Io $io, array $all, array $copied, array $packages): void
    {
        if ($all === []) {
            $io->errorLine(
                sprintf(
                    'No environment files to copy found in "%s" package%s...',
                    implode('", "', $packages),
                    (count($packages) === 1) ? '' : 's'
                )
            );
            return;
        }

        if ($all === $copied) {
            $io->infoLine(
                sprintf(
                    'Environment file%s "%s" copied!',
                    count($copied) === 1 ? '' : 's',
                    implode('", "', $copied)
                )
            );
            return;
        }

        $notCopied = array_diff($all, $copied);
        $io->errorLine(
            sprintf(
                'Failed copying environment file%s "%s".',
                count($notCopied) === 1 ? '' : 's',
                implode('", "', $notCopied)
            )
        );
    }
}
