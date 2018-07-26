<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the vip-composer-plugin package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.NoAccessors
 */

declare(strict_types=1);

namespace Inpsyde\VipComposer;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;

class Plugin implements PluginInterface, EventSubscriberInterface, Capable, CommandProvider
{
    public const NAME = 'inpsyde/vip-composer-plugin';

    /**
     * @var Installer\Installer
     */
    private $installer;

    /**
     * @var Installer\NoopCoreInstaller
     */
    private $noopCoreInstaller;

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            PackageEvents::PRE_PACKAGE_INSTALL => ['prePackage', PHP_INT_MAX],
            PackageEvents::PRE_PACKAGE_UPDATE => ['prePackage', PHP_INT_MAX],
            PackageEvents::PRE_PACKAGE_UNINSTALL => ['prePackage', PHP_INT_MAX],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getCapabilities()
    {
        return [CommandProvider::class => __CLASS__];
    }

    /**
     * @inheritdoc
     */
    public function getCommands()
    {
        return [new Command()];
    }

    /**
     * @inheritdoc
     */
    public function activate(Composer $composer, IOInterface $composerIo)
    {
        $factory = new Factory($composer, $composerIo);
        $this->installer = $factory->installer();
        $this->noopCoreInstaller = new Installer\NoopCoreInstaller($factory->io());

        $manager = $composer->getInstallationManager();
        $manager->addInstaller($this->installer);
        $manager->addInstaller($this->noopCoreInstaller);
    }

    /**
     * @param PackageEvent $event
     */
    public function prePackage(PackageEvent $event)
    {
        $operation = $event->getOperation();
        $package = null;
        $operation instanceof UpdateOperation and $package = $operation->getTargetPackage();
        $operation instanceof InstallOperation and $package = $operation->getPackage();

        if ($package && $package->getType() !== 'composer-plugin') {
            $manager = $event->getComposer()->getInstallationManager();
            $manager->addInstaller($this->installer);
            $manager->addInstaller($this->noopCoreInstaller);
        }
    }
}
