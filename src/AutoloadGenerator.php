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

use Composer\Autoload\AutoloadGenerator as ComposerAutoloadGenerator;
use Composer\Composer;
use Composer\IO\IOInterface;

class AutoloadGenerator
{
    const PROD_AUTOLOAD_DIR = 'vip-autoload';

    /**
     * @var InstalledPackages
     */
    private $installedPackages;

    /**
     * @var Directories
     */
    private $directories;

    /**
     * @param InstalledPackages $devPackages
     * @param Directories $directories
     */
    public function __construct(InstalledPackages $devPackages, Directories $directories)
    {
        $this->installedPackages = $devPackages;
        $this->directories = $directories;
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

        $autoloader = new ComposerAutoloadGenerator($composer->getEventDispatcher());
        $autoloader->setDevMode(false);
        $autoloader->setApcu(false);
        $autoloader->setClassMapAuthoritative(true);
        $autoloader->setRunScripts(false);

        $suffix = '';
        $lockFile = $this->directories->basePath().'/composer.lock';
        if (is_readable($lockFile)) {
            $data = @json_decode(file_get_contents($lockFile) ?: '', true);
            $suffix = $data['content-hash'] ?? '';
        }

        $autoloader->dump(
            $composer->getConfig(),
            $this->installedPackages->noDevRepository(),
            $composer->getPackage(),
            $composer->getInstallationManager(),
            self::PROD_AUTOLOAD_DIR,
            true,
            $suffix ?: md5(uniqid('', true))
        );

        $autoloadEntrypoint = "<?php\nrequire_once __DIR__ . '/autoload_real.php';\n";
        $autoloadEntrypoint .= "ComposerAutoloaderInit{$suffix}::getLoader();\n";
        $path = $composer->getConfig()->get('vendor-dir') . '/' . self::PROD_AUTOLOAD_DIR;

        file_put_contents("{$path}/autoload.php", $autoloadEntrypoint);
        file_put_contents($composerPath, $composerContent);
    }
}
