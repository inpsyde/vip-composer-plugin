<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;

/*
 * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.NoAccessors
 */
class Plugin implements PluginInterface, Capable, CommandProvider
{
    public const NAME = 'inpsyde/vip-composer-plugin';

    /**
     * @return array<string, string>
     */
    public function getCapabilities()
    {
        return [CommandProvider::class => __CLASS__];
    }

    /**
     * @return \Composer\Command\BaseCommand[]|Command[]
     */
    public function getCommands()
    {
        return [new Command()];
    }

    /**
     * @param Composer $composer
     * @param IOInterface $io
     * @return void
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $factory = new Factory($composer, $io);
        $manager = $composer->getInstallationManager();
        $manager->addInstaller($factory->installer());

        $this->disableWordPressDefaultInstaller($composer);
        $this->dumpProdAutoload($composer, $factory);
    }

    /**
     * @param Composer $composer
     * @param IOInterface $io
     * @return void
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
        // noop
    }

    /**
     * @param Composer $composer
     * @param IOInterface $io
     * @return void
     */
    public function uninstall(Composer $composer, IOInterface $io)
    {
        // noop
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

    /**
     * @param Composer $composer
     * @param Factory $factory
     * @return void
     */
    private function dumpProdAutoload(Composer $composer, Factory $factory): void
    {
        $dispatcher = $composer->getEventDispatcher();

        $doing = false;
        $callback = static function () use ($factory, $dispatcher, &$doing, &$callback): void {
            if ($doing) {
                $dispatcher->removeListener($callback);

                return;
            }
            $doing = true;
            $config = new Task\TaskConfig([Task\TaskConfig::PROD_AUTOLOAD => true]);
            $taskFactory = new Task\Factory($factory, $config);
            $taskFactory->generateProductionAutoload()->autorun();
        };

        $dispatcher->addListener(ScriptEvents::POST_AUTOLOAD_DUMP, $callback, PHP_INT_MAX);
    }
}
