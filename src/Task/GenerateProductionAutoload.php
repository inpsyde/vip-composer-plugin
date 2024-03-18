<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Task;

use Composer\Autoload\AutoloadGenerator;
use Composer\Composer;
use Composer\Filter\PlatformRequirementFilter\PlatformRequirementFilterFactory;
use Inpsyde\VipComposer\Config;
use Inpsyde\VipComposer\Utils\InstalledPackages;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\VipDirectories;

final class GenerateProductionAutoload implements Task
{
    /**
     * @param Config $config
     * @param Composer $composer
     * @param InstalledPackages $devPackages
     * @param VipDirectories $directories
     */
    public function __construct(
        private Config $config,
        private Composer $composer,
        private InstalledPackages $installedPackages,
        private VipDirectories $directories
    ) {
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
        return $taskConfig->isGit()
            || $taskConfig->generateProdAutoload()
            || $taskConfig->isVipDevEnv();
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
        $prodAutoloadDirname = $this->config->prodAutoloadDir();

        $autoloader = $this->factoryAutoloadGenerator();

        $suffix = '_VIP_' . md5(uniqid('', true));
        $config = clone $this->composer->getConfig();
        $config->merge(['config' => ['autoloader-suffix' => $suffix]]);

        $composerAutoloadContents = @file_get_contents("{$vendorDir}/autoload.php");

        $autoloader->dump(
            $config,
            $this->installedPackages->noDevRepository(),
            $this->composer->getPackage(),
            $this->composer->getInstallationManager(),
            $prodAutoloadDirname,
            true,
            ''
        );

        $vipAutoloadPath = "{$vendorDir}/{$prodAutoloadDirname}";

        $loaderClass = $this->determineLoaderClassName($vipAutoloadPath, $suffix);

        $this->replaceVipPaths($vipAutoloadPath);
        $this->replaceStaticLoader($vipAutoloadPath);

        $autoloadEntrypoint = "<?php\n\nrequire_once __DIR__ . '/autoload_real.php';\n";
        $autoloadEntrypoint .= "return {$loaderClass}::getLoader();\n";

        if (!file_put_contents("{$vipAutoloadPath}/autoload.php", $autoloadEntrypoint)) {
            throw new \Error('Error generating production autoload: failed wring entrypoint file.');
        }
        if ($composerAutoloadContents) {
            file_put_contents("{$vendorDir}/autoload.php", $composerAutoloadContents);
        }

        $io->infoLine('Done!');
    }

    /**
     * @return AutoloadGenerator
     */
    private function factoryAutoloadGenerator(): AutoloadGenerator
    {
        $autoloader = new AutoloadGenerator($this->composer->getEventDispatcher());
        $autoloader->setDevMode(false);
        $autoloader->setClassMapAuthoritative(true);
        $autoloader->setApcu(false);
        $autoloader->setRunScripts(false);
        if (class_exists(PlatformRequirementFilterFactory::class)) {
            $filter = PlatformRequirementFilterFactory::ignoreNothing();
            $autoloader->setPlatformRequirementFilter($filter);
        }

        return $autoloader;
    }

    /**
     * @param string $vipAutoloadPath
     * @param string $suffix
     * @return string
     */
    private function determineLoaderClassName(string $vipAutoloadPath, string $suffix): string
    {
        $declaredClasses = get_declared_classes();
        require "{$vipAutoloadPath}/autoload_real.php";
        $declaredClasses = array_diff(get_declared_classes(), $declaredClasses);
        if (count($declaredClasses) !== 1) {
            throw new \Error('Error loading generated production autoload class.');
        }

        $loaderClass = reset($declaredClasses);

        $suffixLength = -1 * strlen($suffix);
        if (
            (substr($loaderClass, $suffixLength) !== $suffix)
            || !is_callable([$loaderClass, 'getLoader'])
        ) {
            throw new \Error('Error generating production autoload: suffix does not match.');
        }

        return $loaderClass;
    }

    /**
     * @param string $vipAutoloadPath
     */
    private function replaceVipPaths(string $vipAutoloadPath): void
    {
        $vendorDir = '$vendorDir = WPCOM_VIP_CLIENT_MU_PLUGIN_DIR . \'/vendor\';';
        $baseDir = '$baseDir = ABSPATH;';
        $vipDirBase = basename($this->directories->targetPath());

        $toReplace = [
            'autoload_classmap.php',
            'autoload_files.php',
            'autoload_namespaces.php',
            'autoload_psr4.php',
        ];

        foreach ($toReplace as $file) {
            if (!file_exists("{$vipAutoloadPath}/{$file}")) {
                continue;
            }

            $content = file_get_contents("{$vipAutoloadPath}/{$file}") ?: '';
            $content = preg_replace('~\$vendorDir\s*=\s*[^;]+;~', $vendorDir, $content, 1);
            $content = preg_replace('~\$baseDir\s*=\s*[^;]+;~', $baseDir, $content ?: '', 1);
            $content = preg_replace(
                '~\$baseDir\s*\.\s*\'/' . $vipDirBase . '/(client-mu-plugins|plugins|themes)/~',
                'WP_CONTENT_DIR . \'/$1/',
                $content ?: ''
            );
            file_put_contents("{$vipAutoloadPath}/{$file}", (string)$content);
        }
    }

    /**
     * @param string $vipAutoloadPath
     * @return void
     */
    private function replaceStaticLoader(string $vipAutoloadPath): void
    {
        $filepath = "{$vipAutoloadPath}/autoload_static.php";
        $contents = file_get_contents($filepath) ?: '';

        $vip = basename($this->directories->targetPath());

        $exclude = [
            $this->directories->privateDir(),
            $this->directories->phpConfigDir(),
            $this->directories->yamlConfigDir(),
        ];

        $paths = '';
        foreach ($this->directories->toArray() as $path) {
            if (!in_array($path, $exclude, true)) {
                $paths and $paths .= '|';
                $paths .= basename($path);
            }
        }

        $contents = preg_replace(
            '~=>.+?' . "{$vip}(/(?:{$paths})[^']+',)~",
            '=> WP_CONTENT_DIR . \'$1',
            $contents,
        );

        if (!$contents) {
            throw new \Error('Generation of production static autoloaded class failed.');
        }

        file_put_contents($filepath, $contents);
    }
}
