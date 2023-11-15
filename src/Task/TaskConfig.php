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

namespace Inpsyde\VipComposer\Task;

/**
 * @psalm-type config-data = array{
 *      'deploy': bool,
 *      'git': bool,
 *      'git-url': string|null,
 *      'git-branch': string|null,
 *      'local': bool,
 *      'prod-autoload': bool,
 *      'push': bool,
 *      'skip-vip-mu-plugins': bool,
 *      'skip-wp': bool,
 *      'sync-dev-paths': bool,
 *      'update-vip-mu-plugins': bool,
 *      'update-wp': bool,
 *      'vip-dev-env': bool
 *  }
 */
final class TaskConfig
{
    public const DEPLOY = 'deploy';
    public const FORCE_CORE_UPDATE = 'update-wp';
    public const FORCE_VIP_MU = 'update-vip-mu-plugins';
    public const GIT_NO_PUSH = 'git';
    public const GIT_BRANCH = 'git-branch';
    public const GIT_PUSH = 'push';
    public const GIT_URL = 'git-url';
    public const LOCAL = 'local';
    public const PROD_AUTOLOAD = 'prod-autoload';
    public const SKIP_CORE_UPDATE = 'skip-wp';
    public const SKIP_VIP_MU = 'skip-vip-mu-plugins';
    public const SYNC_DEV_PATHS = 'sync-dev-paths';
    public const VIP_DEV_ENV = 'vip-dev-env';

    private const DEFAULTS = [
        self::DEPLOY => false,
        self::FORCE_CORE_UPDATE => false,
        self::FORCE_VIP_MU => false,
        self::GIT_BRANCH => null,
        self::GIT_NO_PUSH => false,
        self::GIT_PUSH => false,
        self::GIT_URL => null,
        self::LOCAL => false,
        self::PROD_AUTOLOAD => false,
        self::SKIP_CORE_UPDATE => false,
        self::SKIP_VIP_MU => false,
        self::SYNC_DEV_PATHS => false,
        self::VIP_DEV_ENV => false,
    ];

    private const FILTERS = [
        self::DEPLOY => [
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'flags' => FILTER_NULL_ON_FAILURE,
        ],
        self::FORCE_CORE_UPDATE => [
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'flags' => FILTER_NULL_ON_FAILURE,
        ],
        self::FORCE_VIP_MU => [
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'flags' => FILTER_NULL_ON_FAILURE,
        ],
        self::GIT_BRANCH => [
            'filter' => FILTER_UNSAFE_RAW,
        ],
        self::GIT_NO_PUSH => [
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'flags' => FILTER_NULL_ON_FAILURE,
        ],
        self::GIT_PUSH => [
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'flags' => FILTER_NULL_ON_FAILURE,
        ],
        self::GIT_URL => [
            'filter' => FILTER_UNSAFE_RAW,
        ],
        self::LOCAL => [
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'flags' => FILTER_NULL_ON_FAILURE,
        ],
        self::PROD_AUTOLOAD => [
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'flags' => FILTER_NULL_ON_FAILURE,
        ],
        self::SKIP_CORE_UPDATE => [
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'flags' => FILTER_NULL_ON_FAILURE,
        ],
        self::SKIP_VIP_MU => [
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'flags' => FILTER_NULL_ON_FAILURE,
        ],
        self::SYNC_DEV_PATHS => [
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'flags' => FILTER_NULL_ON_FAILURE,
        ],
        self::VIP_DEV_ENV => [
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'flags' => FILTER_NULL_ON_FAILURE,
        ],
    ];

    /** @var config-data */
    private array $data;

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        $customData = filter_var_array(
            array_replace(self::DEFAULTS, array_intersect_key($data, self::DEFAULTS), $data),
            self::FILTERS,
            false
        );

        /** @psalm-suppress  MixedPropertyTypeCoercion data */
        $this->data = $customData ?: self::DEFAULTS;

        // FILTER_UNSAFE_RAW will convert null into empty string
        foreach (self::FILTERS as $key => $filters) {
            if (
                (($filters['filter'] ?? 0) === FILTER_UNSAFE_RAW)
                && (($data[$key] ?? null) === null)
                && (($this->data[$key] ?? null) === '')
            ) {
                /** @psalm-suppress MixedPropertyTypeCoercion */
                $this->data[$key] = null;
            }
        }

        $this->validate();
    }

    /**
     * @return bool
     */
    public function isLocal(): bool
    {
        return $this->data[self::LOCAL];
    }

    /**
     * @return bool
     */
    public function isOnlyLocal(): bool
    {
        return $this->isLocal() && !$this->isGit();
    }

    /**
     * @return bool
     */
    public function isDeploy(): bool
    {
        return $this->data[self::DEPLOY];
    }

    /**
     * @return bool
     */
    public function isGit(): bool
    {
        return $this->isGitPush() || $this->isGitNoPush();
    }

    /**
     * @return bool
     */
    public function isGitPush(): bool
    {
        return $this->isDeploy() || ($this->isLocal() && $this->data[self::GIT_PUSH]);
    }

    /**
     * @return bool
     */
    public function isGitNoPush(): bool
    {
        return $this->isLocal() && $this->data[self::GIT_NO_PUSH];
    }

    /**
     * @return bool
     */
    public function skipVipMuPlugins(): bool
    {
        return $this->data[self::SKIP_VIP_MU];
    }

    /**
     * @return bool
     */
    public function forceVipMuPlugins(): bool
    {
        return $this->data[self::FORCE_VIP_MU];
    }

    /**
     * @return bool
     */
    public function forceCoreUpdate(): bool
    {
        return $this->data[self::FORCE_CORE_UPDATE];
    }

    /**
     * @return bool
     */
    public function generateProdAutoload(): bool
    {
        return $this->data[self::PROD_AUTOLOAD];
    }

    /**
     * @return bool
     */
    public function skipCoreUpdate(): bool
    {
        return $this->data[self::SKIP_CORE_UPDATE];
    }

    /**
     * @return bool
     */
    public function syncDevPaths(): bool
    {
        return $this->data[self::SYNC_DEV_PATHS];
    }

    /**
     * @return null|string
     */
    public function gitUrl(): ?string
    {
        return $this->isGit() ? $this->data[self::GIT_URL] : null;
    }

    /**
     * @return null|string
     */
    public function gitBranch(): ?string
    {
        return $this->isGit() ? $this->data[self::GIT_BRANCH] : null;
    }

    /**
     * @return bool
     */
    public function isVipDevEnv(): bool
    {
        return $this->data[self::VIP_DEV_ENV];
    }

    /**
     * @return void
     */
    private function validate(): void
    {
        $this->data[self::GIT_BRANCH] = $this->validateBranchName();
        $this->data[self::GIT_URL] = $this->validateUrl();
        $this->validateBooleans();

        if (
            !$this->isLocal()
            && !$this->isDeploy()
            && !$this->syncDevPaths()
            && !$this->forceCoreUpdate()
            && !$this->forceVipMuPlugins()
            && !$this->isVipDevEnv()
        ) {
            $this->data[self::LOCAL] = true;
        }

        if (
            $this->isVipDevEnv()
            && (
                $this->isLocal()
                || $this->isGit()
                || $this->syncDevPaths()
                || $this->forceCoreUpdate()
                || $this->forceVipMuPlugins()
                || $this->generateProdAutoload()
            )
        ) {
            throw new \LogicException('--vip-dev-env must be the *only* option when used.');
        }

        if ($this->syncDevPaths() && ($this->isLocal() || $this->isDeploy())) {
            throw new \LogicException('--sync-dev-paths must be the *only* option when used.');
        }

        if ($this->isLocal() && $this->isDeploy()) {
            throw new \LogicException('Can\'t run both *local* and *deploy* tasks.');
        }

        if ($this->skipCoreUpdate() && $this->forceCoreUpdate()) {
            throw new \LogicException('Can\'t both *skip* and *force* core update.');
        }

        if ($this->skipVipMuPlugins() && $this->forceVipMuPlugins()) {
            throw new \LogicException('Can\'t both *skip* and *force* VIP GO MU plugins update.');
        }
    }

    /**
     * @return non-empty-string|null
     */
    private function validateBranchName(): ?string
    {
        $name = $this->data[self::GIT_BRANCH] ?? null;
        if ($name === null) {
            return null;
        }

        /** @psalm-suppress DocblockTypeContradiction */
        if (!is_string($name) || ($name === '')) {
            throw new \LogicException(sprintf('Invalid command parameter "%s".', self::GIT_BRANCH));
        }

        return $name;
    }

    /**
     * @return non-empty-string|null
     */
    private function validateUrl(): ?string
    {
        $url = $this->data[self::GIT_URL] ?? null;
        if ($url === null) {
            return null;
        }

        /** @psalm-suppress DocblockTypeContradiction */
        if (!is_string($url) || ($url === '')) {
            throw new \LogicException(sprintf('Invalid command parameter "%s".', self::GIT_URL));
        }

        return $url;
    }

    /**
     * @return void
     */
    private function validateBooleans(): void
    {
        $failures = [];
        foreach (self::FILTERS as $key => $filters) {
            if (
                (($filters['filter'] ?? 0) === FILTER_VALIDATE_BOOLEAN)
                && !is_bool($this->data[$key] ?? null)
            ) {
                $failures[] = $key;
            }
        }
        if ($failures) {
            throw new \LogicException(
                sprintf('Invalid command parameter(s) "%s".', implode('", "', $failures))
            );
        }
    }
}
