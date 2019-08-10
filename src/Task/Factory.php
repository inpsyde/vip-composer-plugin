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

namespace Inpsyde\VipComposer\Task;

use Inpsyde\VipComposer\Factory as DependenciesFactory;
use Inpsyde\VipComposer\Tasks;

class Factory
{
    /**
     * @var DependenciesFactory
     */
    private $factory;

    /**
     * @var array
     */
    private $services = [];

    /**
     * @var TaskConfig
     */
    private $taskConfig;

    /**
     * @param DependenciesFactory $factory
     * @param TaskConfig $taskConfig
     */
    public function __construct(DependenciesFactory $factory, TaskConfig $taskConfig)
    {
        $this->factory = $factory;
        $this->taskConfig = $taskConfig;
    }

    /**
     * @return Tasks
     */
    public function tasks(): Tasks
    {
        return $this->service(
            Tasks::class,
            function (): Tasks {
                return new Tasks(
                    $this->factory->config(),
                    $this->taskConfig,
                    $this->factory->vipDirectories(),
                    $this->factory->io(),
                    $this->factory->fileSystem()
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
                    $this->factory->fileSystem()
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
                    $this->factory->fileSystem()
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
                    $this->factory->remoteFileSystem(),
                    $this->factory->fileSystem(),
                    $this->factory->unzipper()
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
                $composer = $packages = $this->factory->composer();
                $lockData = $composer->getLocker()->getLockData();

                return new GenerateMuPluginsLoader(
                    $this->factory->config(),
                    $this->factory->vipDirectories(),
                    $this->factory->wpPluginFileFinder(),
                    $this->factory->fileSystem(),
                    $lockData
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
                    $this->factory->fileSystem(),
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
                    $this->factory->fileSystem()
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
                    $this->factory->fileSystem()
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
     * @param string $class
     * @param callable $factory
     * @return mixed
     *
     * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
     */
    private function service(string $class, callable $factory)
    {
        // phpcs:enable Inpsyde.CodeQuality.ReturnTypeDeclaration

        if (!array_key_exists($class, $this->services)) {
            $this->services[$class] = $factory();
        }

        return $this->services[$class];
    }
}
