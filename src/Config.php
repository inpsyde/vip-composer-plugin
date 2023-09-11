<?php

/**
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
use Composer\Config as ComposerConfig;

/**
 * @template-implements \ArrayAccess<mixed, mixed>
 */
final class Config implements \ArrayAccess
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
            self::WP_VERSION_KEY => '4.9.*',
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
    ];

    /**
     * @var array
     */
    private $config;

    /**
     * @var ComposerConfig
     */
    private $composerConfig;

    /**
     * @param Composer $composer
     * @param string $basePath
     */
    public function __construct(Composer $composer, string $basePath)
    {
        $extra = (array)($composer->getPackage()->getExtra()[self::CONFIG_KEY] ?? []);
        $this->composerConfig = $composer->getConfig();

        $this->config = [];
        $keys = array_keys(self::DEFAULTS);
        foreach ($keys as $key) {
            $this->config[$key] = array_key_exists($key, $extra) && is_array($extra[$key])
                ? array_merge(self::DEFAULTS[$key], $extra[$key])
                : self::DEFAULTS[$key];
        }

        $this->config[self::BASE_PATH_KEY] = $basePath;
        $this->config[self::PROD_AUTOLOAD_DIR_KEY] = 'vip-autoload';
    }

    /**
     * @return string
     */
    public function basePath(): string
    {
        return (string)$this->offsetGet(self::BASE_PATH_KEY);
    }

    /**
     * @return string
     */
    public function prodAutoloadDir(): string
    {
        return (string)$this->offsetGet(self::PROD_AUTOLOAD_DIR_KEY);
    }

    /**
     * @param string $key
     * @return string
     */
    public function composerConfigValue(string $key): string
    {
        return (string)$this->composerConfig->get($key);
    }

    /**
     * @return string
     */
    public function composerLockPath(): string
    {
        /** @var ComposerConfig\ConfigSourceInterface $configSource */
        $configSource = $this->composerConfig->getConfigSource();
        $composerJsonSource = $configSource->getName();

        return (string)preg_replace('~\.json$~', '.lock', $composerJsonSource, 1);
    }

    /**
     * @return array
     */
    public function vipConfig(): array
    {
        return (array)$this->offsetGet(self::VIP_CONFIG_KEY);
    }

    /**
     * @return array
     */
    public function gitConfig(): array
    {
        return (array)$this->offsetGet(self::GIT_CONFIG_KEY);
    }

    /**
     * @return array
     */
    public function wpConfig(): array
    {
        return (array)$this->offsetGet(self::WP_CONFIG_KEY);
    }

    /**
     * @return array
     */
    public function pluginsAutoloadConfig(): array
    {
        return (array)$this->offsetGet(self::PLUGINS_AUTOLOAD_KEY);
    }

    /**
     * @return array
     */
    public function devPathsConfig(): array
    {
        return (array)$this->offsetGet(self::DEV_PATHS_CONFIG_KEY);
    }

    /**
     * @inheritdoc
     */
    public function offsetExists($offset): bool
    {
        return $this->offsetGet($offset) !== null;
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if (!is_string($offset) || !$offset) {
            return null;
        }

        $keys = explode('.', $offset);
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

    /**
     * @inheritdoc
     */
    public function offsetSet($offset, $value): void
    {
        throw new \BadMethodCallException(
            sprintf(
                "Can't execute %s: %s is read-only",
                __METHOD__,
                __CLASS__
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($offset): void
    {
        throw new \BadMethodCallException(
            sprintf(
                "Can't execute %s: %s is read-only",
                __METHOD__,
                __CLASS__
            )
        );
    }
}
