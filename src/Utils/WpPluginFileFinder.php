<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Utils;

use Composer\Package\PackageInterface;
use Inpsyde\VipComposer\Installer;

class WpPluginFileFinder
{
    /**
     * @param Installer $installer
     */
    public function __construct(private Installer $installer)
    {
    }

    /**
     * @param PackageInterface $package
     * @return string
     */
    public function pathForPluginPackage(PackageInterface $package): string
    {
        $path = $this->installer->getInstallPath($package);
        if (!$path) {
            return '';
        }

        $base = basename($path);
        $files = glob("{$path}/*.php");
        if (($files === false) || ($files === [])) {
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
     * @param string $file
     * @return bool
     */
    private function isPluginFile(string $file): bool
    {
        $handle = fopen($file, 'r');
        if (!$handle) {
            return false;
        }

        $data = fread($handle, 8192);
        fclose($handle);
        if ($data === false) {
            return false;
        }

        $data = str_replace("\r", "\n", $data);

        return preg_match('/^[ \t\/*#@]*Plugin Name:(.*)$/mi', $data, $match) && ($match[1] ?? '');
    }
}
