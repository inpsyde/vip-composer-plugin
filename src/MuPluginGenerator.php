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

use Composer\Config;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;

class MuPluginGenerator
{

    /**
     * @var Directories
     */
    private $directories;

    /**
     * @var PluginFileFinder
     */
    private $finder;
    /**
     * @var Config
     */
    private $config;

    /**
     * @param Directories $directories
     * @param Config $config
     * @param PluginFileFinder $finder
     */
    public function __construct(Directories $directories, Config $config, PluginFileFinder $finder)
    {
        $this->directories = $directories;
        $this->config = $config;
        $this->finder = $finder;
    }

    /**
     * @param PackageInterface ...$packages
     * @return bool
     */
    public function generate(PackageInterface ...$packages): bool
    {
        $filesystem = new Filesystem();

        $loaderContent = "<?php\n";
        $loaderContent .= $this->autoloadLoader($filesystem);
        $loaderContent .= $this->vipLoadPluginFunction();
        $muPluginsPath = $this->directories->muPluginsDir();

        $done = [];

        foreach ($packages as $package) {
            $packageName = $package->getName();
            if (!$packageName || !is_string($packageName) || in_array($packageName, $done, true)) {
                continue;
            }

            $type = $package->getType();
            if ($type !== 'wordpress-plugin' && $type !== 'wordpress-muplugin') {
                continue;
            }

            $path = $this->finder->pathForPluginPackage($package);
            if (!$path) {
                continue;
            }

            $done[] = $packageName;

            if ($type === 'wordpress-plugin') {
                $loaderContent .= "\nUEFA_IS_LOCAL_ENV\n\t? ";
                $loaderContent .= "wp_register_plugin_realpath(WP_CONTENT_DIR.'/plugins/{$path}')";
                $loaderContent .= "\n\t: wpcom_vip_load_plugin('{$path}');";
                continue;
            }

            $loaderContent .= "\nrequire_once __DIR__ . '/{$path}';";
        }

        return (bool) file_put_contents("{$muPluginsPath}/__loader.php", $loaderContent);
    }

    /**
     * @param Filesystem $filesystem
     * @return string
     */
    private function autoloadLoader(Filesystem $filesystem): string
    {
        $vendorBase = basename($filesystem->normalizePath($this->config->get('vendor-dir')));

        $php = <<<PHP
define('UEFA_IS_LOCAL_ENV', !defined('VIP_GO_ENV') || !VIP_GO_ENV || VIP_GO_ENV === 'local');
UEFA_IS_LOCAL_ENV
    ? require_once __DIR__ . '/$vendorBase/autoload.php'
    : require_once __DIR__ . '/$vendorBase/vip-autoload/autoload.php';
PHP;
        return "{$php}\n\n";
    }

    /**
     * @return string
     */
    private function vipLoadPluginFunction(): string
    {
        $php = <<<'PHP'
if (!function_exists('wpcom_vip_load_plugin')) {
    function wpcom_vip_load_plugin($pluginPath) {
        return $pluginPath;
    }
}
PHP;
        return "{$php}\n";
    }
}
