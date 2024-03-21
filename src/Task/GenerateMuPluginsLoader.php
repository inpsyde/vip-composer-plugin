<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Task;

use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;
use Inpsyde\VipComposer\Config;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\Utils\WpPluginFileFinder;
use Inpsyde\VipComposer\VipDirectories;

final class GenerateMuPluginsLoader implements Task
{
    /** @var list<PackageInterface> $packages */
    private array $packages;

    /**
     * @param Config $config
     * @param VipDirectories $directories
     * @param WpPluginFileFinder $finder
     * @param Filesystem $filesystem
     * @param PackageInterface[] $packages
     *
     * @no-named-arguments
     */
    public function __construct(
        private Config $config,
        private VipDirectories $directories,
        private WpPluginFileFinder $finder,
        private Filesystem $filesystem,
        PackageInterface ...$packages
    ) {

        $this->packages = $packages;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return 'Generating loader for Composer autoload and plugins';
    }

    /**
     * @param TaskConfig $taskConfig
     * @return bool
     */
    public function enabled(TaskConfig $taskConfig): bool
    {
        return $this->packages
            && ($taskConfig->isLocal() || $taskConfig->isDeploy() || $taskConfig->isVipDevEnv());
    }

    /**
     * @param Io $io
     * @param TaskConfig $taskConfig
     * @return void
     */
    public function run(Io $io, TaskConfig $taskConfig): void
    {
        $io->verboseCommentLine('Start with Composer loader...');

        $muPluginsPath = $this->directories->muPluginsDir();
        $loaderPath = "{$muPluginsPath}/__loader.php";

        $autoloadCode = $this->autoloadCode();

        if (file_put_contents($loaderPath, "<?php\n{$autoloadCode}") === false) {
            throw new \RuntimeException('Failed writing loader.');
        }

        $io->verboseInfoLine('Composer loader written.');
        $io->verboseCommentLine('Now proceeding with plugins loader...');

        [$packagesList, $includeByDefault] = $this->buildIncludeConfig();

        $packagesLoaderCode = '';
        $donePackages = [];
        foreach ($this->packages as $package) {
            $packageName = $package->getName();
            $type = $package->getType();

            if (
                !$packageName
                || !in_array($type, ['wordpress-plugin', 'wordpress-muplugin'], true)
                || in_array($packageName, $donePackages, true)
            ) {
                continue;
            }

            if (
                ($type === 'wordpress-plugin')
                && !$this->shouldInclude($package, $packagesList, $includeByDefault)
            ) {
                $io->verboseLine(" - skipping <comment>{$packageName}</comment>");

                continue;
            }

            $packagesLoaderCode .= $this->loaderCodeForPackage($package, $io);
            $donePackages[] = $packageName;
        }

        if (!$donePackages) {
            $io->verboseInfoLine("No WP packages to write loader for.");
            $io->infoLine("Loaders generation complete.");

            return;
        }

        $php = "<?php\n{$autoloadCode}\n{$packagesLoaderCode}";
        if (file_put_contents($loaderPath, $php) !== false) {
            $io->infoLine("Loaders generation complete.");
            return;
        }

        $error = "Error: loaders file could not be written to {$loaderPath}";
        if ($taskConfig->isOnlyLocal()) {
            $io->errorLine($error);
            return;
        }

        throw new \RuntimeException($error);
    }

    /**
     * @return array{array<int,string>, bool}
     */
    private function buildIncludeConfig(): array
    {
        $config = $this->config->pluginsAutoloadConfig();

        $includeRaw = $config[Config::PLUGINS_AUTOLOAD_INCLUDE_KEY] ?? [];
        $excludeRaw = $config[Config::PLUGINS_AUTOLOAD_EXCLUDE_KEY] ?? [];

        $include = is_array($includeRaw) ? array_filter($includeRaw, 'is_string') : [];
        $exclude = is_array($excludeRaw) ? array_filter($excludeRaw, 'is_string') : [];

        $includeUnique = $include ? array_values(array_unique($include)) : [];
        $excludeUnique = $exclude ? array_values(array_unique($exclude)) : [];

        $byDefault = !($includeUnique || $excludeUnique) || $excludeUnique;

        return [$excludeUnique ?: $includeUnique, $byDefault];
    }

    /**
     * @param PackageInterface $package
     * @param Io $io
     * @return string
     */
    private function loaderCodeForPackage(PackageInterface $package, Io $io): string
    {
        $name = $package->getName();
        $type = $package->getType();

        $path = $this->finder->pathForPluginPackage($package);
        if (!$path) {
            $io->verboseInfoLine("Could not find path for package {$name} of type {$type}.");

            return '';
        }

        $code = $this->registerRealPathCode($path, $type);
        $code .= $type === 'wordpress-plugin'
            ? "\nwpcom_vip_load_plugin('{$path}');\n\n"
            : "\nrequire_once realpath(__DIR__ . '/{$path}');\n\n";

        return $code;
    }

    /**
     * @return string
     */
    private function autoloadCode(): string
    {
        $vendorDir = $this->config->composerConfigValue('vendor-dir');
        $vendorBase = basename($this->filesystem->normalizePath($vendorDir));

        $php = <<<PHP
\$vipEnv = defined('VIP_GO_APP_ENVIRONMENT')
    ? VIP_GO_APP_ENVIRONMENT
    : (defined('VIP_GO_ENV') ? VIP_GO_ENV : 'local');
\$isWipDevEnv = getenv('VIP_DEV_AUTOLOGIN_KEY') && (getenv('LANDO') === 'ON');

define('VIP_GO_IS_LOCAL_ENV', (!\$vipEnv || \$vipEnv === 'local'));
define('VIP_GO_IS_DEV_ENV', \$isWipDevEnv);
unset(\$vipEnv, \$isWipDevEnv);

(VIP_GO_IS_LOCAL_ENV && !VIP_GO_IS_DEV_ENV)
    ? require_once __DIR__ . "/{$vendorBase}/autoload.php"
    : require_once __DIR__ . "/{$vendorBase}/vip-autoload/autoload.php";


PHP;
        $php .= <<<'PHP'
if (VIP_GO_IS_LOCAL_ENV && VIP_GO_IS_DEV_ENV) {
    add_filter('https_ssl_verify', '__return_false');
}

if (!function_exists('wpcom_vip_load_plugin')) {
    function wpcom_vip_load_plugin($path) {
        require_once dirname(__DIR__) . "/plugins/{$path}";
    }
}
PHP;
        return "{$php}\n";
    }

    /**
     * @param string $path
     * @param string $type
     * @return string
     */
    private function registerRealPathCode(string $path, string $type): string
    {
        $wpDirName = $this->config->wpConfig()[Config::WP_LOCAL_DIR_KEY];
        $folder = $type === 'wordpress-plugin' ? 'plugins' : 'client-mu-plugins';
        $fromPath = $this->directories->muPluginsDir() . '/__loader.php';
        $toFolder = "/{$wpDirName}/wp-content/{$folder}/{$path}";
        $toPath = $this->filesystem->normalizePath($this->config->basePath() . $toFolder);
        $relative = $this->filesystem->findShortestPathCode($fromPath, $toPath, false);

        $php = <<<PHP
(VIP_GO_IS_LOCAL_ENV && !VIP_GO_IS_DEV_ENV) and wp_register_plugin_realpath({$relative});
PHP;
        return $php;
    }

    /**
     * @param PackageInterface $package
     * @param array<int,string> $packagesList
     * @param bool $byDefault
     * @return bool
     */
    private function shouldInclude(
        PackageInterface $package,
        array $packagesList,
        bool $byDefault
    ): bool {

        $name = $package->getName();

        // exact match always win
        $exactMatch = in_array($name, $packagesList, true);
        if ($exactMatch) {
            return !$byDefault;
        }

        foreach ($packagesList as $pattern) {
            if (!str_contains($pattern, '*')) {
                continue;
            }

            $pattern === '*' and $pattern = '*/*';
            if (fnmatch($pattern, $name, FNM_PATHNAME | FNM_PERIOD | FNM_CASEFOLD)) {
                return !$byDefault;
            }
        }

        return $byDefault;
    }
}
