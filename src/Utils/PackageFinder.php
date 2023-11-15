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

namespace Inpsyde\VipComposer\Utils;

use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryInterface;

class PackageFinder
{
    /** @var list<PackageInterface>|null */
    private ?array $packages = null;

    /**
     * @param RepositoryInterface $packageRepo
     */
    public function __construct(private RepositoryInterface $packageRepo)
    {
    }

    /**
     * @param string $type
     * @return array<PackageInterface>
     */
    public function findByType(string $type): array
    {
        if (!$type) {
            return [];
        }

        $list = [];
        $packages = $this->all();

        foreach ($packages as $package) {
            if ($package->getType() === $type) {
                $list[] = $package;
            }
        }

        return $list;
    }

    /**
     * @param string $vendor
     * @return PackageInterface[]
     */
    public function findByVendor(string $vendor): array
    {
        if (!$vendor) {
            return [];
        }

        $list = [];
        $packages = $this->all();

        $vendor = rtrim($vendor, '/') . '/';

        foreach ($packages as $package) {
            if (
                stripos($package->getPrettyName(), $vendor) === 0
                || stripos($package->getName(), $vendor) === 0
            ) {
                $list[] = $package;
            }
        }

        return $list;
    }

    /**
     * @return array<PackageInterface>
     *
     * @psalm-assert list<PackageInterface> $this->packages
     */
    public function all(): array
    {
        if (!is_array($this->packages)) {
            $this->packages = array_values($this->packageRepo->getPackages());
        }

        return $this->packages;
    }
}
