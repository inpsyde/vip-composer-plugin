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

/**
 * @psalm-type config-data = array{
 *      'deploy': bool|null,
 *      'local': bool|null,
 *      'git': bool|null,
 *      'push': bool|null,
 *      'git-url': string|null,
 *      'git-branch': string|null,
 *      'update-vip-mu-plugins': bool|null,
 *      'skip-vip-mu-plugins': bool|null,
 *      'update-wp': bool|null,
 *      'skip-wp': bool|null,
 *      'sync-dev-paths': bool|null
 *  }
 */
final class TaskConfig
{
    public const DEPLOY = 'deploy';
    public const LOCAL = 'local';
    public const GIT_NO_PUSH = 'git';
    public const GIT_PUSH = 'push';
    public const GIT_URL = 'git-url';
    public const GIT_BRANCH = 'git-branch';
    public const FORCE_VIP_MU = 'update-vip-mu-plugins';
    public const SKIP_VIP_MU = 'skip-vip-mu-plugins';
    public const FORCE_CORE_UPDATE = 'update-wp';
    public const SKIP_CORE_UPDATE = 'skip-wp';
    public const SYNC_DEV_PATHS = 'sync-dev-paths';

    /**
     * @psalm-var config-data
     */
    private const DEFAULTS = [
        self::DEPLOY => false,
        self::LOCAL => false,
        self::GIT_NO_PUSH => false,
        self::GIT_PUSH => false,
        self::GIT_URL => null,
        self::GIT_BRANCH => null,
        self::FORCE_VIP_MU => false,
        self::SKIP_VIP_MU => false,
        self::FORCE_CORE_UPDATE => false,
        self::SKIP_CORE_UPDATE => false,
        self::SYNC_DEV_PATHS => false,
    ];

    private const FILTERS = [
        self::DEPLOY => FILTER_VALIDATE_BOOLEAN,
        self::LOCAL => FILTER_VALIDATE_BOOLEAN,
        self::GIT_NO_PUSH => FILTER_VALIDATE_BOOLEAN,
        self::GIT_PUSH => FILTER_VALIDATE_BOOLEAN,
        self::GIT_URL => FILTER_SANITIZE_URL,
        self::GIT_BRANCH => FILTER_UNSAFE_RAW,
        self::FORCE_VIP_MU => FILTER_VALIDATE_BOOLEAN,
        self::SKIP_VIP_MU => FILTER_VALIDATE_BOOLEAN,
        self::FORCE_CORE_UPDATE => FILTER_VALIDATE_BOOLEAN,
        self::SKIP_CORE_UPDATE => FILTER_VALIDATE_BOOLEAN,
        self::SYNC_DEV_PATHS => FILTER_VALIDATE_BOOLEAN,
    ];

    /**
     * @var array
     * @psalm-var config-data
     */
    private $data;

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        /** @var config-data|false $customData */
        $customData = filter_var_array(
            array_intersect_key($data, self::DEFAULTS),
            self::FILTERS,
            false
        );

        $this->data = $customData
            ? array_merge(self::DEFAULTS, $customData)
            : self::DEFAULTS;

        $this->validate();
    }

    /**
     * @return bool
     */
    public function isLocal(): bool
    {
        return (bool)$this->data[self::LOCAL];
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
        return (bool)$this->data[self::DEPLOY];
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
        return (bool)$this->data[self::SKIP_VIP_MU];
    }

    /**
     * @return bool
     */
    public function forceVipMuPlugins(): bool
    {
        return (bool)$this->data[self::FORCE_VIP_MU];
    }

    /**
     * @return bool
     */
    public function forceCoreUpdate(): bool
    {
        return (bool)$this->data[self::FORCE_CORE_UPDATE];
    }

    /**
     * @return bool
     */
    public function skipCoreUpdate(): bool
    {
        return (bool)$this->data[self::SKIP_CORE_UPDATE];
    }

    /**
     * @return bool
     */
    public function syncDevPaths(): bool
    {
        return (bool)$this->data[self::SYNC_DEV_PATHS];
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
     * @return void
     */
    private function validate(): void
    {
        $branch = $this->data[self::GIT_BRANCH] ?? null;
        /** @see https://git-scm.com/docs/git-check-ref-format */
        $regex = '{^(?!/|.*([/.]\.|//|@\{|\\\\))[^\040\177 ~^:?*\[]+(?<!\.lock|[/.])$}';
        if (!is_string($branch) || ($branch === '') || !preg_match($regex, $branch)) {
            throw new \LogicException(sprintf('Invalid configuration for "%s".', self::GIT_BRANCH));
        }

        /** @psalm-suppress DocblockTypeContradiction */
        if (!is_string($this->data[self::GIT_URL] ?? '')) {
            throw new \LogicException(sprintf('Invalid configuration for "%s".', self::GIT_URL));
        }

        if (
            !$this->isLocal()
            && !$this->isDeploy()
            && !$this->syncDevPaths()
            && !$this->forceCoreUpdate()
            && !$this->forceVipMuPlugins()
        ) {
            $this->data[self::LOCAL] = true;
        }

        if ($this->syncDevPaths() && ($this->isLocal() || $this->isDeploy())) {
            throw new \LogicException('Sync dev path must be the *only* option when used.');
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
}
