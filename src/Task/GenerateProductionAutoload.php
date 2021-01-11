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

namespace Inpsyde\VipComposer\Task;

use Composer\Autoload\AutoloadGenerator;
use Composer\Composer;
use Inpsyde\VipComposer\Config;
use Inpsyde\VipComposer\Utils\InstalledPackages;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\VipDirectories;

final class GenerateProductionAutoload implements Task
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var InstalledPackages
     */
    private $installedPackages;

    /**
     * @var VipDirectories
     */
    private $directories;

    /**
     * @param Config $config
     * @param Composer $composer
     * @param InstalledPackages $devPackages
     * @param VipDirectories $directories
     */
    public function __construct(
        Config $config,
        Composer $composer,
        InstalledPackages $devPackages,
        VipDirectories $directories
    ) {

        $this->config = $config;
        $this->composer = $composer;
        $this->installedPackages = $devPackages;
        $this->directories = $directories;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return 'Generate production autoloader';
    }

    /**
     * @param TaskConfig $taskConfig
     * @return bool
     */
    public function enabled(TaskConfig $taskConfig): bool
    {
        return $taskConfig->isGit();
    }

    /**
     * @param Io $io
     * @param TaskConfig $taskConfig
     * @return void
     */
    public function run(Io $io, TaskConfig $taskConfig): void
    {
        $io->commentLine('Building production autoload...');

        $vendorDir = $this->config->composerConfigValue('vendor-dir');
        $autoloader = new AutoloadGenerator($this->composer->getEventDispatcher());
        $autoloader->setDevMode(false);
        $autoloader->setApcu(false);
        $autoloader->setClassMapAuthoritative(true);
        $autoloader->setRunScripts(false);

        $suffix = '';
        $lockFile = $this->config->composerLockPath();
        if (is_readable($lockFile)) {
            $data = json_decode(file_get_contents($lockFile) ?: '', true);
            $suffix = $data['content-hash'] ?? '';
        }

        $prodAutoloadDirname = $this->config->prodAutoloadDir();

        $autoloader->dump(
            $this->composer->getConfig(),
            $this->installedPackages->noDevRepository(),
            $this->composer->getPackage(),
            $this->composer->getInstallationManager(),
            $prodAutoloadDirname,
            true,
            $suffix ?: md5(uniqid('', true))
        );

        $autoloadEntrypoint = "<?php\nrequire_once __DIR__ . '/autoload_real.php';\n";
        $autoloadEntrypoint .= "ComposerAutoloaderInit{$suffix}::getLoader();\n";
        $path = "{$vendorDir}/{$prodAutoloadDirname}";

        file_put_contents("{$path}/autoload.php", $autoloadEntrypoint);

        $this->replaceVipPaths($path);

        $io->infoLine('Done!');
    }

    /**
     * @param string $path
     */
    private function replaceVipPaths(string $path): void
    {
        $vendorDir = '$vendorDir = WPCOM_VIP_CLIENT_MU_PLUGIN_DIR . \'/vendor\';';
        $baseDir = '$baseDir = ABSPATH;';
        $staticLoader = '$useStaticLoader = false;';
        $vipDirBase = basename($this->directories->targetPath());

        $toReplace = [
            'autoload_classmap.php',
            'autoload_files.php',
            'autoload_namespaces.php',
            'autoload_namespaces.php',
            'autoload_psr4.php',
        ];

        foreach ($toReplace as $file) {
            if (!file_exists("{$path}/{$file}")) {
                continue;
            }

            $content = file_get_contents("{$path}/{$file}") ?: '';
            $content = preg_replace('~\$vendorDir(?:\s*=\s*)[^;]+;~', $vendorDir, $content, 1);
            $content = preg_replace('~\$baseDir(?:\s*=\s*)[^;]+;~', $baseDir, $content ?: '', 1);
            $content = preg_replace(
                '~\$baseDir\s*\.\s*\'/' . $vipDirBase . '/(plugins|themes)/~',
                'WP_CONTENT_DIR . \'/$1/',
                $content ?: ''
            );
            file_put_contents("{$path}/{$file}", (string)$content);
        }

        $real = file_get_contents("{$path}/autoload_real.php") ?: '';
        $real = preg_replace('~\$useStaticLoader(?:\s*=\s*)[^;]+;~', $staticLoader, $real, 1);
        file_put_contents("{$path}/autoload_real.php", $real);
    }
}
