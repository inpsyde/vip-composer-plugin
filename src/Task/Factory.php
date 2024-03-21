<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Task;

use Inpsyde\VipComposer\Factory as DependenciesFactory;
use Inpsyde\VipComposer\Tasks;

class Factory
{
    /** @var array<string, object> */
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
        /** @var Tasks $tasks */
        $tasks = $this->service(
            Tasks::class,
            function (): Tasks {
                return new Tasks(
                    $this->factory->config(),
                    $this->taskConfig,
                    $this->factory->io()
                );
            }
        );

        return $tasks;
    }

    /**
     * @return CopyDevPaths
     */
    public function copyDevPaths(): CopyDevPaths
    {
        /** @var CopyDevPaths $copyDevPaths */
        $copyDevPaths = $this->service(
            CopyDevPaths::class,
            function (): CopyDevPaths {
                return new CopyDevPaths(
                    $this->factory->config(),
                    $this->factory->vipDirectories(),
                    $this->factory->packageFinder(),
                    $this->factory->composer()->getInstallationManager(),
                    $this->factory->filesystem()
                );
            }
        );

        return $copyDevPaths;
    }

    /**
     * @return CopyEnvConfigs
     */
    public function copyEnvConfig(): CopyEnvConfigs
    {
        /** @var CopyEnvConfigs $copyEnvConfigs */
        $copyEnvConfigs = $this->service(
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

        return $copyEnvConfigs;
    }

    /**
     * @return DownloadVipGoMuPlugins
     */
    public function downloadVipGoMuPlugins(): DownloadVipGoMuPlugins
    {
        /** @var DownloadVipGoMuPlugins $downloadVipGoMuPlugins */
        $downloadVipGoMuPlugins = $this->service(
            DownloadVipGoMuPlugins::class,
            function (): DownloadVipGoMuPlugins {
                return new DownloadVipGoMuPlugins(
                    $this->factory->config(),
                    $this->factory->vipDirectories(),
                    $this->factory->filesystem()
                );
            }
        );

        return $downloadVipGoMuPlugins;
    }

    /**
     * @return DownloadWpCore
     */
    public function downloadWpCore(): DownloadWpCore
    {
        /** @var DownloadWpCore $downloadWpCore */
        $downloadWpCore = $this->service(
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

        return $downloadWpCore;
    }

    /**
     * @return GenerateMuPluginsLoader
     */
    public function generateMuPluginsLoader(): GenerateMuPluginsLoader
    {
        /** @var GenerateMuPluginsLoader $generateMuPluginsLoader */
        $generateMuPluginsLoader = $this->service(
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

        return $generateMuPluginsLoader;
    }

    /**
     * @return GenerateProductionAutoload
     */
    public function generateProductionAutoload(): GenerateProductionAutoload
    {
        /** @var GenerateProductionAutoload $generateProductionAutoload */
        $generateProductionAutoload = $this->service(
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

        return $generateProductionAutoload;
    }

    /**
     * @return HandleGit
     */
    public function handleGit(): HandleGit
    {
        /** @var HandleGit $handleGit */
        $handleGit = $this->service(
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

        return $handleGit;
    }

    /**
     * @return SymlinkVipGoDir
     */
    public function symlinkVipGoDir(): SymlinkVipGoDir
    {
        /** @var SymlinkVipGoDir $symlinkVipGoDir */
        $symlinkVipGoDir = $this->service(
            SymlinkVipGoDir::class,
            function (): SymlinkVipGoDir {
                return new SymlinkVipGoDir(
                    $this->factory->config(),
                    $this->factory->vipDirectories(),
                    $this->factory->filesystem()
                );
            }
        );

        return $symlinkVipGoDir;
    }

    /**
     * @return UpdateLocalWpConfigFile
     */
    public function updateLocalWpConfigFile(): UpdateLocalWpConfigFile
    {
        /** @var UpdateLocalWpConfigFile $updateLocalWpConfigFile */
        $updateLocalWpConfigFile = $this->service(
            UpdateLocalWpConfigFile::class,
            function (): UpdateLocalWpConfigFile {
                return new UpdateLocalWpConfigFile(
                    $this->factory->config(),
                    $this->factory->vipDirectories(),
                    $this->factory->filesystem()
                );
            }
        );

        return $updateLocalWpConfigFile;
    }

    /**
     * @return GenerateDeployVersion
     */
    public function generateDeployVersion(): GenerateDeployVersion
    {
        /** @var GenerateDeployVersion $deployVersion */
        $deployVersion = $this->service(
            GenerateDeployVersion::class,
            function (): GenerateDeployVersion {
                return new GenerateDeployVersion($this->factory->vipDirectories());
            }
        );

        return $deployVersion;
    }

    /**
     * @return EnsureGitKeep
     */
    public function ensureGitKeep(): EnsureGitKeep
    {
        /** @var EnsureGitKeep $gitKeep */
        $gitKeep = $this->service(
            EnsureGitKeep::class,
            function (): EnsureGitKeep {
                return new EnsureGitKeep(
                    $this->factory->vipDirectories(),
                    $this->factory->filesystem()
                );
            }
        );

        return $gitKeep;
    }

    /**
     * @param string $class
     * @param callable():object $factory
     * @return object
     */
    private function service(string $class, callable $factory): object
    {
        if (!array_key_exists($class, $this->services)) {
            $this->services[$class] = $factory();
        }

        return $this->services[$class];
    }
}
