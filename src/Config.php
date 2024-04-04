<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer;

use Composer\Composer;
use Composer\Config as ComposerConfig;

final class Config
{
    public const CONFIG_KEY = 'vip-composer';

    public const BASE_PATH_KEY = 'base-path';
    public const PROD_AUTOLOAD_DIR_KEY = 'prod-autoload-dir';

    public const VIP_CONFIG_KEY = 'vip';
    public const VIP_LOCAL_DIR_KEY = 'local-dir';
    public const VIP_MUPLUGINS_LOCAL_DIR_KEY = 'muplugins-local-dir';

    public const GIT_CONFIG_KEY = 'git';
    public const GIT_URL_KEY = 'url';
    public const GIT_BRANCH_KEY = 'branch';

    public const WP_CONFIG_KEY = 'wordpress';
    public const WP_VERSION_KEY = 'version';
    public const WP_LOCAL_DIR_KEY = 'local-dir';
    public const WP_LOCAL_UPLOADS_DIR_KEY = 'uploads-local-dir';

    public const PLUGINS_AUTOLOAD_KEY = 'plugins-autoload';
    public const PLUGINS_AUTOLOAD_INCLUDE_KEY = 'include';
    public const PLUGINS_AUTOLOAD_EXCLUDE_KEY = 'exclude';

    public const DEV_PATHS_CONFIG_KEY = 'dev-paths';
    public const DEV_PATHS_MUPLUGINS_DIR_KEY = 'muplugins-dir';
    public const DEV_PATHS_PLUGINS_DIR_KEY = 'plugins-dir';
    public const DEV_PATHS_THEMES_DIR_KEY = 'themes-dir';
    public const DEV_PATHS_LANGUAGES_DIR_KEY = 'languages-dir';
    public const DEV_PATHS_IMAGES_DIR_KEY = 'images-dir';
    public const DEV_PATHS_PHP_CONFIG_DIR_KEY = 'vip-config-dir';
    public const DEV_PATHS_YAML_CONFIG_DIR_KEY = 'config-dir';
    public const DEV_PATHS_PRIVATE_DIR_KEY = 'private-dir';

    public const CUSTOM_ENV_NAMES_KEY = 'custom-env-names';

    public const PACKAGE_TYPE_MULTI_MU_PLUGINS = 'wordpress-multiple-mu-plugins';

    public const DEFAULTS = [
        self::VIP_CONFIG_KEY => [
            self::VIP_LOCAL_DIR_KEY => 'vip',
            self::VIP_MUPLUGINS_LOCAL_DIR_KEY => 'vip-go-mu-plugins',
        ],
        self::GIT_CONFIG_KEY => [
            self::GIT_URL_KEY => '',
            self::GIT_BRANCH_KEY => '',
        ],
        self::WP_CONFIG_KEY => [
            self::WP_VERSION_KEY => 'latest',
            self::WP_LOCAL_DIR_KEY => 'public',
            self::WP_LOCAL_UPLOADS_DIR_KEY => 'uploads',
        ],
        self::PLUGINS_AUTOLOAD_KEY => [
            self::PLUGINS_AUTOLOAD_INCLUDE_KEY => [],
            self::PLUGINS_AUTOLOAD_EXCLUDE_KEY => [],
        ],
        self::DEV_PATHS_CONFIG_KEY => [
            self::DEV_PATHS_MUPLUGINS_DIR_KEY => 'mu-plugins',
            self::DEV_PATHS_PLUGINS_DIR_KEY => 'plugins',
            self::DEV_PATHS_THEMES_DIR_KEY => 'themes',
            self::DEV_PATHS_LANGUAGES_DIR_KEY => 'languages',
            self::DEV_PATHS_IMAGES_DIR_KEY => 'images',
            self::DEV_PATHS_PHP_CONFIG_DIR_KEY => 'vip-config',
            self::DEV_PATHS_YAML_CONFIG_DIR_KEY => 'config',
            self::DEV_PATHS_PRIVATE_DIR_KEY => 'private',
        ],
        self::CUSTOM_ENV_NAMES_KEY => [],
    ];

    /**
     *  WordPress supports by default: `local`, `development`, `staging`, and `production`.
     *  VIP supports by default several environments, see:
     *  https://github.com/Automattic/vip-go-mu-plugins/blob/5241ac59dd8c826848f5614f73761e806567c954/000-vip-init.php#L280-L288
     *  This list targets both for larger by-default compatibility.
     */
    private const CUSTOM_ENV_DEFAULTS = [
        'local',
        'dev',
        'develop',
        'development',
        'staging',
        'stage',
        'testing',
        'uat',
        'preprod',
        'production',
        'all',
    ];

    private array $config;
    private ComposerConfig $composerConfig;

    /**
     * @param Composer $composer
     * @param string $basePath
     */
    public function __construct(Composer $composer, string $basePath)
    {
        $extra = (array) ($composer->getPackage()->getExtra()[self::CONFIG_KEY] ?? []);
        $this->composerConfig = $composer->getConfig();

        $this->config = [
            self::BASE_PATH_KEY => $basePath,
            self::PROD_AUTOLOAD_DIR_KEY => 'vip-autoload',
        ];
        $keys = array_keys(self::DEFAULTS);
        foreach ($keys as $key) {
            $this->config[$key] = array_key_exists($key, $extra) && is_array($extra[$key])
                ? array_merge(self::DEFAULTS[$key], $extra[$key])
                : self::DEFAULTS[$key];
        }
    }

    /**
     * @return string
     */
    public function basePath(): string
    {
        return $this->readString(self::BASE_PATH_KEY);
    }

    /**
     * @return string
     */
    public function prodAutoloadDir(): string
    {
        return $this->readString(self::PROD_AUTOLOAD_DIR_KEY);
    }

    /**
     * @param string $key
     * @return string
     */
    public function composerConfigValue(string $key): string
    {
        $data = $this->composerConfig->get($key);

        return is_string($data) ? $data : '';
    }

    /**
     * @return string
     */
    public function composerLockPath(): string
    {
        $configSource = $this->composerConfig->getConfigSource();
        $composerJsonSource = $configSource->getName();

        return (string) preg_replace('~\.json$~', '.lock', $composerJsonSource, 1);
    }

    /**
     * @return array
     */
    public function vipConfig(): array
    {
        return $this->readArray(self::VIP_CONFIG_KEY);
    }

    /**
     * @return array
     */
    public function gitConfig(): array
    {
        return $this->readArray(self::GIT_CONFIG_KEY);
    }

    /**
     * @return array
     */
    public function wpConfig(): array
    {
        return $this->readArray(self::WP_CONFIG_KEY);
    }

    /**
     * @return array
     */
    public function pluginsAutoloadConfig(): array
    {
        return $this->readArray(self::PLUGINS_AUTOLOAD_KEY);
    }

    /**
     * @return array
     */
    public function devPathsConfig(): array
    {
        return $this->readArray(self::DEV_PATHS_CONFIG_KEY);
    }

    /**
     * @return list<non-empty-string>
     */
    public function envConfigs(): array
    {
        $customEnvs = $this->readArray(self::CUSTOM_ENV_NAMES_KEY);
        if ($customEnvs === []) {
            return self::CUSTOM_ENV_DEFAULTS;
        }
        $envNames = [];
        foreach ($customEnvs as $envName) {
            if (($envName === '') || !is_string($envName)) {
                continue;
            }
            $envName = trim(strtolower($envName));
            if (
                preg_match('~^[a-z][a-z0-9_\.\-]+$~', $envName)
                && !in_array($envName, $envNames, true)
            ) {
                $envNames[] = $envName;
            }
        }
        /** @var list<non-empty-string> $envNames */
        return $envNames;
    }

    /**
     * @return string
     */
    public function pluginPath(): string
    {
        return dirname(__DIR__);
    }

    /**
     * @param non-empty-string $key
     * @return string
     */
    private function readString(string $key): string
    {
        $data = $this->read($key);

        return is_string($data) ? $data : '';
    }

    /**
     * @param non-empty-string $key
     * @return array
     */
    private function readArray(string $key): array
    {
        $data = $this->read($key);

        return is_array($data) ? $data : [];
    }

    /**
     * @param non-empty-string $key
     * @return mixed
     */
    private function read(string $key): mixed
    {
        $keys = explode('.', $key);
        $target = $this->config;
        while ($keys) {
            $key = array_shift($keys);
            if (!is_array($target) || !array_key_exists($key, $target)) {
                return null;
            }

            $target = $target[$key];
        }

        return $target;
    }
}
