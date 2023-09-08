<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Utils;

use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryInterface;
use Composer\Util\Filesystem as ComposerFilesystem;

class PackageFinder
{
    /**
     * @var RepositoryInterface
     */
    private $packageRepo;

    /**
     * @var array<PackageInterface>|null
     */
    private $packages;

    /**
     * @param RepositoryInterface $packageRepo
     */
    public function __construct(RepositoryInterface $packageRepo)
    {
        $this->packageRepo = $packageRepo;
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
                continue;
            }
        }

        return $list;
    }

    /**
     * @return array<PackageInterface>
     *
     * @psalm-assert array<PackageInterface> $this->packages
     */
    public function all(): array
    {
        if (!is_array($this->packages)) {
            $this->packages = $this->packageRepo->getPackages();
        }

        return $this->packages;
    }
}
