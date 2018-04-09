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

use Composer\Package\PackageInterface;

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
     * @param Directories $directories
     * @param PluginFileFinder $finder
     */
    public function __construct(Directories $directories, PluginFileFinder $finder)
    {
        $this->directories = $directories;
        $this->finder = $finder;
    }

    /**
     * @param PackageInterface[] ...$packages
     * @return bool
     */
    public function generate(PackageInterface ...$packages): bool
    {
        $muContent = "<?php\n";
        $muContent .= $this->autoloadLoader();
        $muContent .= $this->vipLoadPluginFunction();
        $muPluginPath = $this->directories->muPluginsDir();

        foreach ($packages as $package) {
            $packageName = $package->getName();
            if (!$packageName || !is_string($packageName)) {
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

            if ($type === 'wordpress-plugin') {
                $muContent .= "\nwpcom_vip_load_plugin('{$path}');";
                continue;
            }

            $muContent .= "\nrequire_once '{$muPluginPath}/{$path}';";
        }

        return (bool) file_put_contents("{$muPluginPath}/__loader.php", $muContent);
    }

    /**
     * @return string
     */
    private function autoloadLoader(): string
    {
        $php = <<<'PHP'
if (defined('VIP_GO_ENV')) {
    require_once WPCOM_VIP_PRIVATE_DIR . '/vip-autoload/autoload.php';
    
    return;
}

require_once dirname(__DIR__) . "/private/autoload.php";

PHP;
        return $php;
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
        return $php;
    }
}
