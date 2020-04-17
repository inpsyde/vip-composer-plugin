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

namespace Inpsyde\VipComposer\Utils;

use Composer\Package\PackageInterface;
use Inpsyde\VipComposer\Installer\Installer;

class WpPluginFileFinder
{

    /**
     * @var Installer
     */
    private $installer;

    /**
     * @param Installer $installer
     */
    public function __construct(Installer $installer)
    {
        $this->installer = $installer;
    }

    /**
     * @inheritdoc
     */
    public function pathForPluginPackage(PackageInterface $package): string
    {
        $path = $this->installer->getInstallPath($package);
        if (!$path) {
            return '';
        }

        $base = basename($path);
        $files = glob("{$path}/*.php");
        if (!$files) {
            return '';
        }

        foreach ($files as $file) {
            if ($this->isPluginFile($file)) {
                return "{$base}/" . basename($file);
            }
        }

        return '';
    }

    /**
     * @param $file
     * @return bool
     */
    private function isPluginFile(string $file): bool
    {
        $handle = fopen($file, 'r');
        $data = fread($handle, 8192);
        fclose($handle);
        if (!$data) {
            return false;
        }

        $data = str_replace("\r", "\n", $data);

        return preg_match('/^[ \t\/*#@]*Plugin Name:(.*)$/mi', $data, $match) && ($match[1] ?? '');
    }
}
