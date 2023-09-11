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
 *      'local': bool,
 *      'git': bool,
 *      'push': bool,
 *      'git-url': string,
 *      'git-branch': string,
 *      'update-vip-mu-plugins': bool,
 *      'skip-vip-mu-plugins': bool,
 *      'update-wp': bool,
 *      'skip-wp': bool,
 *      'sync-dev-paths': bool
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
        self::DEPLOY => [
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'flags' => FILTER_NULL_ON_FAILURE,
        ],
        self::LOCAL => [
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'flags' => FILTER_NULL_ON_FAILURE,
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
            'filter' => FILTER_SANITIZE_URL,
            'flags' => FILTER_NULL_ON_FAILURE,
        ],
        self::GIT_BRANCH => [
            'filter' => FILTER_UNSAFE_RAW,
            'flags' => FILTER_NULL_ON_FAILURE,
        ],
        self::FORCE_VIP_MU => [
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'flags' => FILTER_NULL_ON_FAILURE,
        ],
        self::SKIP_VIP_MU => [
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'flags' => FILTER_NULL_ON_FAILURE,
        ],
        self::FORCE_CORE_UPDATE => [
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'flags' => FILTER_NULL_ON_FAILURE,
        ],
        self::SKIP_CORE_UPDATE => [
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'flags' => FILTER_NULL_ON_FAILURE,
        ],
        self::SYNC_DEV_PATHS => [
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'flags' => FILTER_NULL_ON_FAILURE,
        ],
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
        $customData = filter_var_array(
            array_replace(self::DEFAULTS, array_intersect_key($data, self::DEFAULTS), $data),
            self::FILTERS,
            false
        );

        /** @psalm-suppress  MixedPropertyTypeCoercion data */
        $this->data = $customData ?: self::DEFAULTS;

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
     * @return void
     */
    private function validate(): void
    {
        $this->validateBranchName();
        $this->validateUrl();
        $this->validateBooleans();

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

    /**
     * @return void
     *
     * @see https://git-scm.com/docs/git-check-ref-format
     */
    private function validateBranchName(): void
    {
        $error = sprintf('Invalid configuration for "%s".', self::GIT_BRANCH);

        $name = $this->data[self::GIT_BRANCH] ?? null;
        if (!is_string($name) || ($name === '')) {
            throw new \LogicException($error);
        }

        $invalidChars = array_merge(
            range(chr(0), chr(40)),
            [chr(177), '\\', ' ', '~', '^', ':', '?', '*', '[']
        );

        if (in_array($name, $invalidChars, true) || ($name === '@')) {
            throw new \LogicException($error);
        }

        if ((trim($name, '/') !== $name) || (rtrim($name, '.') !== $name)) {
            throw new \LogicException($error);
        }

        $invalidChars = array_map('preg_quote', $invalidChars);
        if (preg_match('#' . implode('|', $invalidChars) . '|/\.|/{2,}|\.{2,}|@\{#', $name)) {
            throw new \LogicException($error);
        }
    }

    /**
     * @return void
     */
    private function validateUrl(): void
    {
        $error = sprintf('Invalid configuration for "%s".', self::GIT_URL);

        $url = $this->data[self::GIT_URL] ?? null;
        /** @psalm-suppress DocblockTypeContradiction */
        if (!is_string($url) || ($url === '')) {
            throw new \LogicException($error);
        }

        if (preg_match('~^(?:git|ssh)@github.com:([^/]+/.+)$~i', $url, $matches)) {
            $url = 'https://github.com/' . $matches[1];
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \LogicException("{$error} Please provide a valid GitHub repository URL.");
        }

        if (!preg_match('~^https://github.com/[^/]+/[^/.]+(?:/|\.git)?$~i', $url, $matches)) {
            throw new \LogicException("{$error} Please provide a valid GitHub repository URL.");
        }
    }

    /**
     * @return void
     */
    private function validateBooleans(): void
    {
        $failures = [];
        foreach (self::FILTERS as $key => $filters) {
            if (
                ($filters['filter'] === FILTER_VALIDATE_BOOLEAN)
                && !is_bool($this->data[$key] ?? null)
            ) {
                $failures[] = $key;
            }
        }
        if ($failures) {
            throw new \LogicException(
                sprintf('Invalid configuration for "%s".', implode('", "', $failures))
            );
        }
    }
}
