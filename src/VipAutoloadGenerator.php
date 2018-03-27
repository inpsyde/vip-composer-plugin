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
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledFilesystemRepository;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryInterface;

class VipAutoloadGenerator
{
    const PROD_AUTOLOAD_DIR = 'vip-autoload';

    private $devPackages = [];

    /**
     * @param Composer $composer
     * @param IOInterface $io
     * @throws \Exception
     */
    public function generate(Composer $composer, IOInterface $io)
    {
        $composerPath = $composer->getConfig()->get('vendor-dir') . '/autoload.php';
        $composerContent = file_get_contents($composerPath);
        $this->devPackages = [];

        $autoloader = new AutoloadGenerator($composer->getEventDispatcher());
        $autoloader->setDevMode(false);
        $autoloader->setApcu(false);
        $autoloader->setClassMapAuthoritative(true);
        $autoloader->setRunScripts(false);

        $suffix = md5(uniqid('', true));

        $autoloader->dump(
            $composer->getConfig(),
            $this->noDevRepository($composer),
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

        if ($this->devPackages) {
            $gitIgnoreBuilder = new GitIgnoreBuilder($composer->getConfig(), $io);
            $gitIgnoreBuilder->build($this->devPackages);
            $this->devPackages = [];
        }
    }

    /**
     * @param Composer $composer
     * @return InstalledRepositoryInterface
     */
    private function noDevRepository(Composer $composer): InstalledRepositoryInterface
    {
        /** @var InstalledFilesystemRepository $repository */
        $repository = clone $composer->getRepositoryManager()->getLocalRepository();

        $devRequires = $composer->getPackage()->getDevRequires();
        $devPackages = $this->findDevPackages($devRequires, $repository, []);
        $devPackages and $devPackages = array_unique($devPackages);

        $this->devPackages = $devPackages;

        $all = $repository->getPackages();
        foreach ($all as $package) {
            $name = $package->getName();
            if (array_key_exists($name, $devPackages) || $name === Plugin::NAME) {
                $repository->removePackage($package);
            }

            $dependents = $repository->getDependents($name);
            if (!$dependents) {
                continue;
            }

            $found = $this->parseDependents($dependents);
            if (array_intersect($found, $devPackages) === $found) {
                $repository->removePackage($package);
            }
        }

        $repository->removePackage($composer->getPackage());

        return $repository;
    }

    /**
     * @param array $requires
     * @param RepositoryInterface $repository
     * @param array $found
     * @return string[]
     */
    private function findDevPackages(
        array $requires,
        RepositoryInterface $repository,
        array $found = []
    ): array {

        foreach ($requires as $link) {
            $package = $repository->findPackage($link->getTarget(), '*');
            if ($package) {
                $found[] = $package->getName();
                $found = $this->findDevPackages($package->getRequires(), $repository, $found);
            }
        }

        return $found;
    }

    /**
     * @param array $dependents
     * @return string[]
     */
    private function parseDependents(array $dependents)
    {
        $because = [];
        foreach ($dependents as list($package)) {
            if ($package instanceof PackageInterface) {
                $because[] = $package->getName();
            }
        }

        return $because;
    }
}
