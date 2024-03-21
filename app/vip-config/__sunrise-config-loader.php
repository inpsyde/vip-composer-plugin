<?php

declare(strict_types=1);

namespace Inpsyde\Vip;

/**
 * @psalm-type config-item = array{
 *       'target': string,
 *       'redirect': bool,
 *       'preservePath': bool,
 *       'preserveQuery': bool
 *   }
 */
class SunriseConfigLoader
{
    /**
     * @var null|array<non-empty-string, array{
     *        'target': string,
     *        'redirect': bool,
     *        'preservePath': bool,
     *        'preserveQuery': bool
     *    }>
     */
    private array|null $config = null;

    /**
     * @param string $dir
     * @param string $vipEnv
     * @param string $wpEnv
     */
    public function __construct(
        private string $dir,
        private string $vipEnv,
        private string $wpEnv,
    ) {
    }

    /**
     * Load the configuration for redirections and domain mapping for a specific domain.
     *
     * @param string $domain
     * @return config-item
     */
    public function loadForDomain(string $domain): array
    {
        return $this->load()[$domain] ?? [
            'target' => '',
            'redirect' => false,
            'preservePath' => false,
            'preserveQuery' => false,
        ];
    }

    /**
     * Load the entire configuration for redirections and domain mapping that is placed in a
     * `sunrise-config.php` or `sunrise-config.json` file.
     *
     * @return array<non-empty-string, config-item>
     */
    private function load(): array
    {
        if ($this->config !== null) {
            return $this->config;
        }

        $this->config = [];

        $data = $this->loadFile();
        $envConfig = $data["env:{$this->vipEnv}"] ?? $data["env:{$this->wpEnv}"] ?? [];

        foreach ($envConfig as $key => $value) {
            /** @var array-key $key */
            $value = $this->isValidKey($key) ? $this->normalizeValue($value) : null;
            if ($value !== null) {
                unset($data[$key]);
                /** @var non-empty-string $key */
                $this->config[$key] = $value;
            }
        }
        foreach ($data as $key => $value) {
            $value = $this->isValidKey($key) ? $this->normalizeValue($value) : null;
            if ($value !== null) {
                /** @var non-empty-string $key */
                $this->config[$key] = $value;
            }
        }

        return $this->config;
    }

    /**
     * @param array-key $key
     * @return bool
     *
     * @psalm-assert-if-true non-empty-string $key
     */
    private function isValidKey(int|string $key): bool
    {
        return is_string($key) && ($key !== '') && !str_starts_with($key, 'env:');
    }

    /**
     * @param mixed $value
     * @return null|config-item
     */
    private function normalizeValue(mixed $value): ?array
    {
        if (is_string($value)) {
            $value = [
                'target' => $value,
                'redirect' => true,
                'preservePath' => true,
                'preserveQuery' => true,
            ];
        }

        if (!is_array($value)) {
            return null;
        }

        $target = $value['target'] ?? null;
        if (($target === '') || !is_string($target)) {
            return null;
        }

        $redirect = (bool) ($value['redirect'] ?? true);
        $preservePath = ((bool) ($value['preservePath'] ?? true)) && $redirect;
        $preserveQuery = ((bool) ($value['preserveQuery'] ?? true)) && $redirect;

        return compact('target', 'redirect', 'preservePath', 'preserveQuery');
    }

    /**
     * @return array
     */
    private function loadFile(): array
    {
        $sunriseConfig = null;
        if (file_exists($this->dir . '/sunrise-config.php')) {
            $sunriseConfig = include $this->dir . '/sunrise-config.php';
        } elseif (file_exists($this->dir . '/sunrise-config.json')) {
            $data = (string) file_get_contents($this->dir . '/sunrise-config.json');
            $sunriseConfig = json_decode($data, true);
        }

        return is_array($sunriseConfig) ? $sunriseConfig : [];
    }
}
