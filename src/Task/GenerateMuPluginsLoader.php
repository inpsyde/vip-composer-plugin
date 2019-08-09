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

use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;
use Inpsyde\VipComposer\Config;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\Utils\WpPluginFileFinder;
use Inpsyde\VipComposer\VipDirectories;

final class GenerateMuPluginsLoader implements Task
{

    /**
     * @var VipDirectories
     */
    private $directories;

    /**
     * @var WpPluginFileFinder
     */
    private $finder;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var PackageInterface[]
     */
    private $packages;

    /**
     * @param Config $config
     * @param VipDirectories $directories
     * @param WpPluginFileFinder $finder
     * @param Filesystem $filesystem
     * @param PackageInterface[] $packages
     */
    public function __construct(
        Config $config,
        VipDirectories $directories,
        WpPluginFileFinder $finder,
        Filesystem $filesystem,
        PackageInterface ...$packages
    ) {

        $this->directories = $directories;
        $this->config = $config;
        $this->finder = $finder;
        $this->filesystem = $filesystem;
        $this->packages = $packages;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return 'Generate MU plugins loader';
    }

    /**
     * @param TaskConfig $taskConfig
     * @return bool
     */
    public function enabled(TaskConfig $taskConfig): bool
    {
        return (bool)$this->packages && ($taskConfig->isLocal() || $taskConfig->isDeploy());
    }

    /**
     * @param Io $io
     * @param TaskConfig $taskConfig
     * @return void
     */
    public function run(Io $io, TaskConfig $taskConfig): void
    {
        $io->commentLine('Generating MU-plugins loader...');

        $muPluginsPath = $this->directories->muPluginsDir();
        $loaderPath = "{$muPluginsPath}/__loader.php";

        $autoloader = $this->autoloadLoader();
        $autoloadWritten = file_put_contents($loaderPath, "<?php\n{$autoloader}");

        if (!$autoloadWritten) {
            throw new \RuntimeException('Failed writing error autoloader.');
        }

        [$loaderContent, $toDoCount, $donePackages] = array_reduce(
            $this->packages,
            [$this, 'loaderContentForPackage'],
            [$autoloader, 0, []]
        );

        if (!$toDoCount) {
            $io->commentLine('No WP packages to write loader for.');
            return;
        }

        $fileWritten = file_put_contents($loaderPath, "<?php\n{$loaderContent}");
        $allDone = count($donePackages) === $toDoCount;

        if ($allDone && $fileWritten) {
            $io->infoLine("MU-plugins loader written to {$muPluginsPath}/__loader.php");
            return;
        }

        $io->errorLine('Error generating MU-plugins loader:');
        $allDone or $io->errorLine('- Not all MU plugins were properly parsed');
        $fileWritten or $io->errorLine("- Loader file could not be written to {$loaderPath}");

        if (!$taskConfig->isOnlyLocal()) {
            throw new \RuntimeException(
                'Error generating MU-plugins loader:'
                . "\n - Not all MU plugins were properly parsed"
                . "\n - Loader file could not be written to {$loaderPath}"
            );
        }
    }

    /**
     * @param array $carry
     * @param PackageInterface $package
     * @return array
     */
    private function loaderContentForPackage(array $carry, PackageInterface $package): array
    {
        [$content, $toDoCount, $done] = $carry;

        $packageName = $package->getName();
        $type = $package->getType();
        if ((!$packageName || !is_string($packageName) || in_array($packageName, $done, true))
            || ($type !== 'wordpress-plugin' && $type !== 'wordpress-muplugin')
        ) {
            return [$content, $toDoCount, $done];
        }

        $toDoCount++;
        $path = $this->finder->pathForPluginPackage($package);
        if (!$path) {
            return [$content, $toDoCount, $done];
        }

        $done++;

        $content .= $this->registerRealPath($path, $type);
        $content .= $type === 'wordpress-plugin'
            ? "\nwpcom_vip_load_plugin('{$path}');\n\n"
            : "\nrequire_once realpath(__DIR__ . '/{$path}');\n\n";

        return [$content, $toDoCount, $done];
    }

    /**
     * @return string
     */
    private function autoloadLoader(): string
    {
        $vendorDir = $this->config->composerConfigValue('vendor-dir');
        $vendorBase = basename($this->filesystem->normalizePath($vendorDir));

        $php = <<<PHP
define('VIP_GO_IS_LOCAL_ENV', !defined('VIP_GO_ENV') || !VIP_GO_ENV || VIP_GO_ENV === 'local');
VIP_GO_IS_LOCAL_ENV
    ? require_once __DIR__ . '/{$vendorBase}/autoload.php'
    : require_once __DIR__ . '/{$vendorBase}/vip-autoload/autoload.php';
PHP;
        return "{$php}\n";
    }

    /**
     * @param string $path
     * @param string $type
     * @return string
     */
    private function registerRealPath(string $path, string $type): string
    {
        $wpDirName = $this->config->wpConfig()[Config::WP_LOCAL_DIR_KEY];
        $folder = $type === 'wordpress-plugin' ? 'plugins' : 'client-mu-plugins';
        $fromPath = $this->directories->muPluginsDir() . '/__loader.php';
        $toFolder = "/{$wpDirName}/wp-content/{$folder}/{$path}";
        $toPath = $this->filesystem->normalizePath($this->config->basePath() . $toFolder);
        $relative = $this->filesystem->findShortestPathCode($fromPath, $toPath, false);

        $php = <<<PHP
VIP_GO_IS_LOCAL_ENV and wp_register_plugin_realpath({$relative});
PHP;
        return $php;
    }
}
