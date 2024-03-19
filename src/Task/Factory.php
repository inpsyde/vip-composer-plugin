<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Task;

use Inpsyde\VipComposer\Factory as DependenciesFactory;
use Inpsyde\VipComposer\Tasks;

class Factory
{
    /** @var array<class-string<Task|Tasks>, Task|Tasks> */
    private array $services = [];

    /**
     * @param DependenciesFactory $factory
     * @param TaskConfig $taskConfig
     */
    public function __construct(
        private DependenciesFactory $factory,
        private TaskConfig $taskConfig
    ) {
    }

    /**
     * @return Tasks
     */
    public function tasks(): Tasks
    {
        /** @psalm-suppress InvalidArgument */
        return $this->service(
            Tasks::class,
            function (): Tasks {
                return new Tasks(
                    $this->factory->config(),
                    $this->taskConfig,
                    $this->factory->io()
                );
            }
        );
    }

    /**
     * @return CopyAppFiles
     */
    public function copyAppFiles(): CopyAppFiles
    {
        return $this->service(
            CopyAppFiles::class,
            function (): CopyAppFiles {
                return new CopyAppFiles(
                    $this->factory->config(),
                    $this->factory->vipDirectories(),
                    $this->factory->filesystem()
                );
            }
        );
    }

    /**
     * @return CopyDevPaths
     */
    public function copyDevPaths(): CopyDevPaths
    {
        return $this->service(
            CopyDevPaths::class,
            function (): CopyDevPaths {
                return new CopyDevPaths(
                    $this->factory->config(),
                    $this->factory->vipDirectories(),
                    $this->factory->packageFinder(),
                    $this->factory->composer()->getInstallationManager(),
                    $this->factory->filesystem(),
                    $this->copyAppFiles()
                );
            }
        );
    }

    /**
     * @return CopyEnvConfigs
     */
    public function copyEnvConfig(): CopyEnvConfigs
    {
        return $this->service(
            CopyEnvConfigs::class,
            function (): CopyEnvConfigs {
                return new CopyEnvConfigs(
                    $this->factory->config(),
                    $this->factory->vipDirectories(),
                    $this->factory->packageFinder(),
                    $this->factory->composer()->getInstallationManager(),
                    $this->factory->filesystem()
                );
            }
        );
    }

    /**
     * @return DownloadVipGoMuPlugins
     */
    public function downloadVipGoMuPlugins(): DownloadVipGoMuPlugins
    {
        return $this->service(
            DownloadVipGoMuPlugins::class,
            function (): DownloadVipGoMuPlugins {
                return new DownloadVipGoMuPlugins(
                    $this->factory->vipDirectories(),
                    $this->factory->filesystem()
                );
            }
        );
    }

    /**
     * @return DownloadWpCore
     */
    public function downloadWpCore(): DownloadWpCore
    {
        return $this->service(
            DownloadWpCore::class,
            function (): DownloadWpCore {
                return new DownloadWpCore(
                    $this->factory->config(),
                    $this->factory->composer(),
                    $this->factory->filesystem(),
                    $this->factory->httpClient(),
                    $this->factory->archiveDownloaderFactory()
                );
            }
        );
    }

    /**
     * @return GenerateMuPluginsLoader
     */
    public function generateMuPluginsLoader(): GenerateMuPluginsLoader
    {
        return $this->service(
            GenerateMuPluginsLoader::class,
            function (): GenerateMuPluginsLoader {
                $packages = $this->factory->composer()
                    ->getRepositoryManager()
                    ->getLocalRepository()
                    ->getPackages();

                return new GenerateMuPluginsLoader(
                    $this->factory->config(),
                    $this->factory->vipDirectories(),
                    $this->factory->wpPluginFileFinder(),
                    $this->factory->filesystem(),
                    ...array_values($packages)
                );
            }
        );
    }

    /**
     * @return GenerateProductionAutoload
     */
    public function generateProductionAutoload(): GenerateProductionAutoload
    {
        return $this->service(
            GenerateProductionAutoload::class,
            function (): GenerateProductionAutoload {
                return new GenerateProductionAutoload(
                    $this->factory->config(),
                    $this->factory->composer(),
                    $this->factory->installedPackages(),
                    $this->factory->vipDirectories()
                );
            }
        );
    }

    /**
     * @return HandleGit
     */
    public function handleGit(): HandleGit
    {
        return $this->service(
            HandleGit::class,
            function (): HandleGit {
                return new HandleGit(
                    $this->factory->config(),
                    $this->factory->vipDirectories(),
                    $this->factory->installedPackages(),
                    $this->factory->filesystem(),
                    $this->factory->unzipper()
                );
            }
        );
    }

    /**
     * @return SymlinkVipGoDir
     */
    public function symlinkVipGoDir(): SymlinkVipGoDir
    {
        return $this->service(
            SymlinkVipGoDir::class,
            function (): SymlinkVipGoDir {
                return new SymlinkVipGoDir(
                    $this->factory->config(),
                    $this->factory->vipDirectories(),
                    $this->factory->filesystem()
                );
            }
        );
    }

    /**
     * @return UpdateLocalWpConfigFile
     */
    public function updateLocalWpConfigFile(): UpdateLocalWpConfigFile
    {
        return $this->service(
            UpdateLocalWpConfigFile::class,
            function (): UpdateLocalWpConfigFile {
                return new UpdateLocalWpConfigFile(
                    $this->factory->config(),
                    $this->factory->vipDirectories(),
                    $this->factory->filesystem()
                );
            }
        );
    }

    /**
     * @return GenerateDeployVersion
     */
    public function generateDeployVersion(): GenerateDeployVersion
    {
        return $this->service(
            GenerateDeployVersion::class,
            function (): GenerateDeployVersion {
                return new GenerateDeployVersion($this->factory->vipDirectories());
            }
        );
    }

    /**
     * @return EnsureGitKeep
     */
    public function ensureGitKeep(): EnsureGitKeep
    {
        return $this->service(
            EnsureGitKeep::class,
            function (): EnsureGitKeep {
                return new EnsureGitKeep(
                    $this->factory->vipDirectories(),
                    $this->factory->filesystem()
                );
            }
        );
    }

    /**
     * @template T of (Task|Tasks)
     *
     * @param class-string<T> $class
     * @param callable():T $factory
     * @return T
     */
    private function service(string $class, callable $factory): Task|Tasks
    {
        if (!array_key_exists($class, $this->services)) {
            $this->services[$class] = $factory();
        }
        /** @var T */
        return $this->services[$class];
    }
}
