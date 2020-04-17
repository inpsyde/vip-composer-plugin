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

namespace Inpsyde\VipComposer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;

/*
 * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.NoAccessors
 */
class Plugin implements PluginInterface, Capable, CommandProvider
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

        $this->disableWordPressDefaultInstaller($composer);
    }

    /**
     * @param Composer $composer
     */
    private function disableWordPressDefaultInstaller(Composer $composer): void
    {
        $rootPackage = $composer->getPackage();
        $rootPackageExtra = $rootPackage->getExtra();
        $disabledInstallers = $rootPackageExtra['installer-disable'] ?? [];
        if ($disabledInstallers === true) {
            return;
        }

        is_array($disabledInstallers) or $disabledInstallers = [];
        $disabledInstallers[] = 'wordpress'; // phpcs:ignore
        $rootPackageExtra['installer-disable'] = $disabledInstallers;
        $rootPackage->setExtra($rootPackageExtra);
    }
}
