<?php

declare(strict_types=1);

namespace Inpsyde\Vip;

/**
 * @psalm-type config-item = array{
 *     "target": string|callable|null,
 *     "redirect": bool,
 *     "status": int,
 *     "preservePath": bool,
 *     "preserveQuery": bool,
 *     "additionalQueryVars": array|callable,
 *     "filterCallback": callable|null
 * }
 */
class SunriseConfigLoader
{
    /**
     * @var null|array<non-empty-string, array{
     *      "target": string|callable|null,
     *      "redirect": bool,
     *      "status": int,
     *      "preservePath": bool,
     *      "preserveQuery": bool,
     *      "additionalQueryVars": array|callable,
     *      "filterCallback": callable|null
     *    }>
     */
    private array|null $config = null;

    /**
     * @var array{
     *    "redirect": bool,
     *    "status": int,
     *    "preservePath": bool,
     *    "preserveQuery": bool,
     *    "additionalQueryVars": array|callable,
     *    "filterCallback": callable|null
     *  }
     */
    private array $defaultConfig = [
        'redirect' => true,
        'status' => 301,
        'preservePath' => true,
        'preserveQuery' => true,
        'additionalQueryVars' => [],
        'filterCallback' => null,
    ];

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
            'target' => null,
            'redirect' => false,
            'status' => 0,
            'preservePath' => false,
            'preserveQuery' => false,
            'additionalQueryVars' => [],
            'filterCallback' => null,
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
        $envConfig = $this->loadDefaultConfig($data);

        foreach ($envConfig as $key => $value) {
            if ($this->loadValue($key, $value)) {
                unset($data[$key]);
            }
        }
        foreach ($data as $key => $value) {
            $this->loadValue($key, $value);
        }

        return $this->config;
    }

    /**
     * @param array $configData
     * @return array
     */
    private function loadDefaultConfig(array $configData): array
    {
        $envConfig = $configData["env:{$this->vipEnv}"] ?? $configData["env:{$this->wpEnv}"] ?? [];
        try {
            is_callable($envConfig) and $envConfig = $envConfig();
        } catch (\Throwable) {
            $envConfig = [];
        }
        is_array($envConfig) or $envConfig = [];

        $defaultConfig = $envConfig[':default:'] ?? $configData[':default:'] ?? null;
        unset($envConfig[':default:'], $configData[':default:']);

        try {
            is_callable($defaultConfig) and $defaultConfig = $defaultConfig();
        } catch (\Throwable) {
            $defaultConfig = [];
        }
        if (($defaultConfig !== []) && is_array($defaultConfig)) {
            // Adding 'target' key or `$this->normalizeValue()` will fail
            $defaultConfig['target'] = 'default';
            $defaultConfig = $this->normalizeValue($defaultConfig);
            if ($defaultConfig !== null) {
                unset($defaultConfig['target']);
                $this->defaultConfig = $defaultConfig;
            }
        }

        return $envConfig;
    }

    /**
     * @param array-key $key
     * @param mixed $value
     * @return bool
     */
    private function loadValue(int|string $key, mixed $value): bool
    {
        $value = $this->isValidKey($key) ? $this->normalizeValue($value) : null;
        if ($value !== null) {
            /** @var non-empty-string $key */
            $this->config[$key] = $value;

            return true;
        }

        return false;
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
        try {
            is_callable($value) and $value = $value();
        } catch (\Throwable) {
            return null;
        }
        if (is_string($value)) {
            $value = [
                'target' => $value,
                'redirect' => $this->defaultConfig['redirect'],
                'status' => $this->defaultConfig['status'],
                'preservePath' => $this->defaultConfig['preservePath'],
                'preserveQuery' => $this->defaultConfig['preserveQuery'],
                'additionalQueryVars' => $this->defaultConfig['additionalQueryVars'],
                'filterCallback' => $this->defaultConfig['filterCallback'],
            ];
        }

        if (!is_array($value)) {
            return null;
        }

        $target = $value['target'] ?? null;
        if ((($target === '') || !is_string($target)) && !is_callable($target)) {
            return null;
        }

        $redirect = (bool) ($value['redirect'] ?? $this->defaultConfig['redirect']);

        [
            $status,
            $preservePath,
            $preserveQuery,
            $additionalQueryVars,
            $filterCallback,
        ] = $redirect ? $this->normalizeRedirectOptions($value) : [0, false, false, [], null];

        return compact(
            'target',
            'redirect',
            'status',
            'preservePath',
            'preserveQuery',
            'additionalQueryVars',
            'filterCallback'
        );
    }

    /**
     * @return list{int, bool, bool, array|callable, callable|null}
     */
    private function normalizeRedirectOptions(array $input): array
    {
        $statusRaw = $input['status'] ?? $this->defaultConfig['status'];
        $status = is_numeric($statusRaw) ? (int) $statusRaw : 301;

        $preservePath = (bool) ($input['preservePath'] ?? $this->defaultConfig['preservePath']);

        $preserveQuery = (bool) ($input['preserveQuery'] ?? $this->defaultConfig['preserveQuery']);

        $queryVarsRaw = $input['additionalQueryVars']
            ?? $this->defaultConfig['additionalQueryVars'];
        $queryVars = (is_array($queryVarsRaw) || is_callable($queryVarsRaw))
            ? $queryVarsRaw
            : [];

        $filterCallbackRaw = $input['filterCallback'] ?? $this->defaultConfig['filterCallback'];
        $filterCallback = is_callable($filterCallbackRaw) ? $filterCallbackRaw : null;

        return [$status, $preservePath, $preserveQuery, $queryVars, $filterCallback];
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
