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

namespace Inpsyde\VipComposer\Git;

use Composer\Util\Filesystem;
use Inpsyde\VipComposer\Config;
use Inpsyde\VipComposer\Utils\InstalledPackages;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\Utils\Unzipper;
use Inpsyde\VipComposer\VipDirectories;
use Symfony\Component\Finder\Finder;

class VipGit
{
    private const MIRROR_PREFIX = '.vipgit';

    /**
     * @var Io
     */
    private $io;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var VipDirectories
     */
    private $directories;

    /**
     * @var InstalledPackages
     */
    private $packages;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var Unzipper
     */
    private $unzipper;

    /**
     * @var array
     */
    private $gitConfig;

    /**
     * @var GitProcess|null
     */
    private $git;

    /**
     * @param Io $io
     * @param Config $config
     * @param VipDirectories $directories
     * @param InstalledPackages $packages
     * @param Filesystem $filesystem
     * @param Unzipper $unzipper
     */
    public function __construct(
        Io $io,
        Config $config,
        VipDirectories $directories,
        InstalledPackages $packages,
        Filesystem $filesystem,
        Unzipper $unzipper
    ) {

        $this->io = $io;
        $this->config = $config;
        $this->directories = $directories;
        $this->filesystem = $filesystem;
        $this->packages = $packages;
        $this->unzipper = $unzipper;
        $this->gitConfig = $config->gitConfig();
    }

    /**
     * @param string|null $url
     * @param string|null $branch
     * @return bool
     */
    public function push(string $url = null, string $branch = null): bool
    {
        return $this->syncAndPush(true, $url, $branch);
    }

    /**
     * @param string|null $url
     * @param string|null $branch
     * @return bool
     */
    public function sync(string $url = null, string $branch = null): bool
    {
        return $this->syncAndPush(false, $url, $branch);
    }

    /**
     * @return string
     */
    public function mirrorDir(): string
    {
        /** @var string|null $mirrorPath */
        static $mirrorPath;
        if ($mirrorPath) {
            return $mirrorPath;
        }

        $mirror = uniqid(self::MIRROR_PREFIX, false);
        $mirrorPath = $this->directories->targetPath() . "/{$mirror}";
        $this->filesystem->ensureDirectoryExists($mirrorPath);

        return $mirrorPath;
    }

    /**
     * @param bool $push
     * @param string|null $url
     * @param string|null $branch
     * @return bool
     *
     * @psalm-assert GitProcess $this->git
     */
    private function syncAndPush(bool $push, string $url = null, string $branch = null): bool
    {
        $this->io->commentLine('Starting Git sync...');

        $this->cleanupOrphanMirrors();
        $mirrorDir = $this->mirrorDir();

        if (!$mirrorDir) {
            return false;
        }

        $this->git = new GitProcess($this->io, $mirrorDir);

        [$remoteUrl, $remoteBranch] = $this->init($url, $branch);
        if (!$remoteUrl || !$remoteBranch) {
            return false;
        }

        $this->io->commentLine('Preparing production files for Git...');

        $this->wipeMirror($mirrorDir);
        if (!is_dir("{$mirrorDir}/.git")) {
            $this->io->errorLine("{$mirrorDir} is not a Git repo.");
            is_dir($mirrorDir) and $this->filesystem->removeDirectory($mirrorDir);

            return false;
        }

        $this->fillMirror($mirrorDir);

        $operation = $push ? 'merging and pushing' : 'merging';
        $this->io->commentLine("Starting git {$operation} changes...");

        $success = $this->mergeAndPush($mirrorDir, $push, $remoteUrl, $remoteBranch);
        if (!$success) {
            return false;
        }

        $this->io->infoLine(ucfirst("{$operation} done successfully!"));

        return true;
    }

    /**
     * @param string|null $customUrl
     * @param string|null $customBranch
     * @return array{string,string}|array{null,null}
     */
    private function init(string $customUrl = null, string $customBranch = null): array
    {
        /**
         * @vase string|null $httpsUrl
         * @vase string|null $sshUrl
         */
        [$httpsUrl, $sshUrl] = $this->gitUrls($customUrl);
        if (!$httpsUrl && !$sshUrl) {
            return [null, null];
        }

        $url = (string)($sshUrl ?: $httpsUrl);
        $branch = (string)($customBranch ?? $this->gitConfig[Config::GIT_BRANCH_KEY]);

        /** @var GitProcess $this->git */
        [$success] = $this->git->exec("clone {$url} .");

        $success and $this->io->infoLine('Repository initialized.');
        if ($success) {
            $success = $this->checkoutBranch($branch);
        }

        return $success ? [$url, $branch] : [null, null];
    }

    /**
     * @return void
     */
    private function cleanupOrphanMirrors(): void
    {
        $finder = Finder::create()
            ->in($this->directories->targetPath())
            ->ignoreVCS(true)
            ->ignoreDotFiles(false)
            ->depth(0)
            ->directories()
            ->filter(
                static function (\SplFileInfo $info): bool {
                    return strpos($info->getBasename(), self::MIRROR_PREFIX) === 0;
                }
            );

        foreach ($finder as $dir) {
            $this->filesystem->removeDirectory((string)$dir);
        }
    }

    /**
     * @param string $mirrorDir
     * @return void
     */
    private function wipeMirror(string $mirrorDir): void
    {
        $finder = Finder::create()
            ->ignoreVCS(true)
            ->ignoreDotFiles(false)
            ->depth(0)
            ->in($mirrorDir);

        foreach ($finder as $path) {
            $this->filesystem->remove((string)$path);
        }
    }

    /**
     * @param string $mirrorDir
     * @return bool
     */
    private function fillMirror(string $mirrorDir): bool
    {
        $copier = new MirrorCopier($this->io, $this->filesystem, $this->unzipper);
        $this->fillMirrorDirs($copier, $mirrorDir);

        $toCopy = $this->packages->noDevPackages();

        if (!$toCopy) {
            return true;
        }

        return $this->fillMirrorVendor($copier, $mirrorDir);
    }

    /**
     * @param string $mirrorDir
     * @param bool $push
     * @param string $remoteUrl
     * @param string $targetBranch
     * @return bool
     */
    private function mergeAndPush(
        string $mirrorDir,
        bool $push,
        string $remoteUrl,
        string $targetBranch
    ): bool {

        /** @var GitProcess $this->git */

        [$success, $output] = $this->git->exec('branch');
        if (!$success) {
            $this->io->errorLine('Failed reading branches.');

            return false;
        }

        $branches = explode($output, "\n") ?: [];
        $currentBranch = '';
        foreach ($branches as $branch) {
            if (strpos(trim($branch), '* ')) {
                $currentBranch = ltrim($branch, '* ');
            }
        }

        $commands = [];
        $currentBranch === $targetBranch or $commands[] = "checkout {$targetBranch}";
        $commands[] = 'add .';
        $commands[] = 'commit -am "Merge-bot upstream sync."';

        [$success, , $outputs] = $this->git->exec(...$commands);
        $output = implode("\n", $outputs);

        $nothingToDo = strpos($output, 'up-to-date') !== false
            || strpos($output, 'working tree clean') !== false
            || strpos($output, 'nothing to commit') !== false;

        if (!$success) {
            $nothingToDo
                ? $this->nothingToDoMessage($push)
                : $this->io->errorLine($output);

            return $nothingToDo;
        }

        if ($nothingToDo) {
            $this->nothingToDoMessage($push);

            return true;
        }

        $changes = $this->gitStats($push, $mirrorDir);
        if ($changes < 0) {
            $this->io->errorLine('Sorry, failed determining git status.');
            $push
                ? $this->io->commentLine('Will try to push anyway.')
                : $this->io->errorLine("Please check {$mirrorDir}.");
        }

        if (!$changes || !$push) {
            return true;
        }

        $this->io->commentLine("Pushing to <<<{$remoteUrl}>>>");
        /** @var bool $success */
        [$success] = $this->git->exec('push origin');

        return $success;
    }

    /**
     * @param string|null $customUrl
     * @return array{string,string}|array{null,null}
     */
    private function gitUrls(string $customUrl = null): array
    {
        /** @var string $url */
        $url = $customUrl ?? $this->gitConfig[Config::GIT_URL_KEY];

        if (
            !$url
            || !filter_var($url, FILTER_VALIDATE_URL)
            || strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https'
            || strpos((string)parse_url($url, PHP_URL_HOST), 'github.com') === false
        ) {
            $this->io->errorLine("Git repo URL '{$url}' looks wrong.");

            return [null, null];
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!$path || $path === '/') {
            $this->io->errorLine("Git repo URL '{$url}' looks wrong.");

            return [null, null];
        }

        if (pathinfo(basename($url), PATHINFO_EXTENSION) !== 'git') {
            $url .= '.git';
            $path .= '.git';
        }

        return [$url, 'git@github.com:' . ltrim($path, '/')];
    }

    /**
     * @param string $targetBranch
     * @return bool
     */
    private function checkoutBranch(string $targetBranch): bool
    {
        /** @var GitProcess $this->git */

        [$success, $output] = $this->git->exec('branch -a');
        if (!$success) {
            return false;
        }

        $branches = $output ? array_filter(explode("\n", $output)) : [];

        $branchIsThere = false;
        $branchIsCurrent = false;

        $regex = "~(\* )?(?:(?:[a-z]+/)?origin/)?{$targetBranch}" . '$~';
        foreach ($branches as $branch) {
            if (preg_match($regex, trim($branch), $matches)) {
                $branchIsThere = true;
                $branchIsCurrent = ($matches[1] ?? '') === '* ';
            }
        }

        if ($branchIsThere) {
            $branchIsCurrent or $this->git->exec("checkout {$targetBranch}");

            return true;
        }

        $this->io->errorLine("Branch {$targetBranch} not on remote.");

        return false;
    }

    /**
     * @param MirrorCopier $copier
     * @param string $mirrorDir
     */
    private function fillMirrorDirs(MirrorCopier $copier, string $mirrorDir): void
    {
        $paths = [
            $this->directories->languagesDir(),
            $this->directories->pluginsDir(),
            $this->directories->privateDir(),
            $this->directories->themesDir(),
            $this->directories->phpConfigDir(),
            $this->directories->yamlConfigDir(),
            $this->directories->imagesDir(),
        ];

        foreach ($paths as $source) {
            $target = "{$mirrorDir}/" . basename($source);
            $copier->copy($source, $target);
        }

        $muSource = $this->directories->muPluginsDir();
        $vendorDir = $this->config->composerConfigValue('vendor-dir');
        $vendorSource = $this->filesystem->normalizePath($vendorDir);

        $finder = Finder::create()
            ->in($muSource)
            ->ignoreVCS(true)
            ->ignoreDotFiles(true)
            ->depth(0);

        $muTarget = "{$mirrorDir}/" . basename($muSource) . '/';
        $this->filesystem->ensureDirectoryExists($muTarget);

        /** @var \SplFileInfo $info */
        foreach ($finder as $info) {
            $source = $this->filesystem->normalizePath((string)$info);
            if ($source === $vendorSource) {
                continue;
            }
            $target = $muTarget . $info->getBasename();
            $copier->copy($source, $target);
        }
    }

    /**
     * @param MirrorCopier $copier
     * @param string $mirrorDir
     * @return bool
     */
    private function fillMirrorVendor(MirrorCopier $copier, string $mirrorDir): bool
    {
        $vendorDir = $this->config->composerConfigValue('vendor-dir');
        $vendorSource = $this->filesystem->normalizePath($vendorDir);
        $targetPath = $this->directories->targetPath();
        $subdir = $this->filesystem->findShortestPath($targetPath, $vendorSource, true);
        $vendorTarget = "{$mirrorDir}/{$subdir}";
        $this->filesystem->ensureDirectoryExists($vendorTarget);
        $toCopy = $this->packages->noDevPackages();

        $all = 0;
        $done = 0;

        foreach ($toCopy as $package) {
            if (strpos($package->getType(), 'wordpress-') === 0) {
                continue;
            }
            $source = "{$vendorSource}/" . $package->getName();
            $target = "{$vendorTarget}/" . $package->getName();

            if (is_dir($source)) {
                $all++;
                $copier->copy($source, $target) and $done++;
            }
        }

        $prodAutoloadDirname = $this->config->prodAutoloadDir();
        $autoloadSource = "{$vendorSource}/{$prodAutoloadDirname}";
        if (is_dir($autoloadSource)) {
            $all++;
            $autoloadTarget = "{$vendorTarget}/{$prodAutoloadDirname}";
            $copier->copy($autoloadSource, $autoloadTarget) and $done++;
        }

        return $all === $done;
    }

    /**
     * @param bool $push
     * @param string $mirrorDir
     * @return int
     */
    private function gitStats(bool $push, string $mirrorDir): int
    {
        /** @var GitProcess $this->git */

        [$success, $output] = $this->git->exec('diff-tree --no-commit-id --name-status -r HEAD');
        if (!$success) {
            $push or $this->io->infoLine('Changes merged but not pushed.');

            return -1;
        }

        $files = array_filter(array_map('trim', explode("\n", $output)));
        if (!$files) {
            $push or $this->io->infoLine('Changes merged but not pushed.');

            return -1;
        }

        $total = count($files);
        $counts = [
            'M' => 0,
            'A' => 0,
            'D' => 0,
        ];

        array_walk(
            $files,
            static function (string $file) use (&$counts): void {
                /** @var array<string, int> $counts */
                $letter = strtoupper($file[0] ?? '');
                array_key_exists($letter, $counts) and $counts[$letter]++;
            }
        );

        if ($total < 1) {
            $this->nothingToDoMessage($push);

            return 0;
        }

        /** @var array<string, int> $counts */

        $push or $this->io->infoLine('Changes merged but not pushed.');
        $messages = ["Involved a total of {$total} files:"];
        $messages[] = "- {$counts['A']} added;";
        $messages[] = "- {$counts['M']} modified;";
        $messages[] = "- {$counts['D']} deleted.";
        $messages[] = "The folder '{$mirrorDir}' is ready to be pushed.";

        $this->io->lines(Io::COMMENT, ...$messages);

        return $total;
    }

    /**
     * @param bool $push
     */
    private function nothingToDoMessage(bool $push): void
    {
        $this->io->commentLine('Everything is already up-to-date!');
        $push and $this->io->commentLine('Nothing to push.');
    }
}
