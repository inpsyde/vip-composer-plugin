<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the vip-composer-plugin package.
 *
 * (c) © 2018 UEFA. All rights reserved.
 */

declare(strict_types=1);

namespace Uefa\VipComposer;

use Composer\Autoload\AutoloadGenerator;
use Composer\Composer;
use Composer\IO\IOInterface;

class VipAutoloadGenerator
{
    const PROD_AUTOLOAD_DIR = 'vip-autoload';

    /**
     * @var InstalledPackages
     */
    private $installedPackages;

    /**
     * @param InstalledPackages $devPackages
     */
    public function __construct(InstalledPackages $devPackages)
    {
        $this->installedPackages = $devPackages;
    }

    /**
     * @param Composer $composer
     * @param IOInterface $io
     * @throws \Exception
     */
    public function generate(Composer $composer, IOInterface $io)
    {
        $io->write('<info>VIP: Building production autoload...</info>');

        $composerPath = $composer->getConfig()->get('vendor-dir') . '/autoload.php';
        $composerContent = file_get_contents($composerPath);

        $autoloader = new AutoloadGenerator($composer->getEventDispatcher());
        $autoloader->setDevMode(false);
        $autoloader->setApcu(false);
        $autoloader->setClassMapAuthoritative(true);
        $autoloader->setRunScripts(false);

        $suffix = md5(uniqid('', true));

        $autoloader->dump(
            $composer->getConfig(),
            $this->installedPackages->noDevRepository(),
            $composer->getPackage(),
            $composer->getInstallationManager(),
            self::PROD_AUTOLOAD_DIR,
            true,
            $suffix
        );

        $autoloadEntrypoint = "<?php\nrequire_once __DIR__ . '/autoload_real.php';\n";
        $autoloadEntrypoint .= "ComposerAutoloaderInit{$suffix}::getLoader();\n";
        $path = $composer->getConfig()->get('vendor-dir') . '/' . self::PROD_AUTOLOAD_DIR;

        file_put_contents("{$path}/autoload.php", $autoloadEntrypoint);
        file_put_contents($composerPath, $composerContent);
    }
}
