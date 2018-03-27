<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the vip-composer-plugin package.
 *
 * (c) © 2018 UEFA. All rights reserved.
 */

declare(strict_types=1);

namespace Uefa\VipComposer;

use Composer\Package\PackageInterface;

class MuPluginGenerator
{

    /**
     * @var VipSkeleton
     */
    private $skeleton;

    /**
     * @var PluginFileFinder
     */
    private $finder;

    /**
     * @param VipSkeleton $skeleton
     * @param PluginFileFinder $finder
     */
    public function __construct(VipSkeleton $skeleton, PluginFileFinder $finder)
    {
        $this->skeleton = $skeleton;
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
        $muPluginPath = $this->skeleton->muPluginsDir();

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
$autoloaderDir = defined('VIP_GO_ENV') ? '/private/vip-autoload' : '/private';
require_once dirname(__DIR__) . "{$autoloaderDir}/autoload.php";
unset($autoloaderDir);

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
