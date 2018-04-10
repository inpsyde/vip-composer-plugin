<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the vip-composer-plugin package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Inpsyde\VipComposer;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Symfony\Component\Finder\Finder;

class VipGit
{
    const MIRROR_PREFIX = '.vipgit';

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Directories
     */
    private $directories;

    /**
     * @var array
     */
    private $extra;

    /**
     * @var GitProcess
     */
    private $git;

    /**
     * @var null|string
     */
    private $remoteUrl;

    /**
     * @var null|string
     */
    private $sshRemoteUrl;

    /**
     * @var null|string
     */
    private $mirrorDir;

    /**
     * @var bool
     */
    private $doPush = false;

    /**
     * @param IOInterface $io
     * @param Config $config
     * @param Directories $directories
     * @param array $extra
     * @param string|null $remoteGitUrl
     */
    public function __construct(
        IOInterface $io,
        Config $config,
        Directories $directories,
        array $extra,
        string $remoteGitUrl = null
    ) {

        $this->io = $io;
        $this->config = $config;
        $this->directories = $directories;
        $this->extra = $extra[Plugin::VIP_GIT_KEY] ?? [];
        $this->remoteUrl = $remoteGitUrl;
    }

    /**
     * @param Filesystem $filesystem
     * @param InstalledPackages $packages
     * @return bool
     */
    public function push(Filesystem $filesystem, InstalledPackages $packages): bool
    {
        $this->doPush = true;

        return $this->sync($filesystem, $packages);
    }

    /**
     * @param Filesystem $filesystem
     * @param InstalledPackages $packages
     * @return bool
     */
    public function sync(Filesystem $filesystem, InstalledPackages $packages): bool
    {
        $push = $this->doPush;
        $this->doPush = false;
        $this->mirrorDir = '';

        if (!$this->mirrorDir($filesystem)) {
            $this->mirrorDir and $filesystem->removeDirectory($this->mirrorDir);
            return false;
        }

        $this->git = new GitProcess($this->io, $this->mirrorDir);

        if (!$this->init($filesystem)) {
            $this->mirrorDir = '';

            return false;
        }

        $this->io->write('     <comment>Preparing production files for Git...</comment>');

        $this->wipeMirror($filesystem);
        if (!is_dir("{$this->mirrorDir}/.git")) {
            $this->io->writeError("<error>VIP: {$this->mirrorDir} is not a Git repo.</error>");
            is_dir($this->mirrorDir) and $filesystem->removeDirectory($this->mirrorDir);
            $this->mirrorDir = '';

            return false;
        }

        $this->fillMirror($filesystem, $packages);

        $operation = $push ? 'merging and pushing' : 'merging';
        $this->io->write("     <comment>Starting git {$operation} changes...</comment>");

        $success = $this->mergeAndPush($push);
        if (!$success) {
            return false;
        }

        if ($push) {
            $this->io->write('     <comment>Cleaning up...</comment>');
            $filesystem->removeDirectory($this->mirrorDir);
        }

        $this->io->write("<info>VIP: {$operation} done successfully!</info>");

        return $success;
    }

    /**
     * @param Filesystem $filesystem
     * @return bool
     */
    private function init(Filesystem $filesystem): bool
    {
        $this->cleanupOrphanMirrors($filesystem);

        $url = $this->repoUrl();
        if (!$url) {
            return false;
        }

        list($success) = $this->git->exec("clone {$url} .");

        $success and $this->io->write('     <comment>Repository initialized.</comment>');
        if ($success) {
            $targetBranch = $this->extra[Plugin::VIP_GIT_BRANCH_KEY] ?? 'development';
            $success = $this->maybeCreateBranch($targetBranch);
        }

        return $success;
    }

    /**
     * @param Filesystem $filesystem
     */
    private function cleanupOrphanMirrors(Filesystem $filesystem)
    {
        $finder = Finder::create()
            ->in($this->directories->targetPath())
            ->ignoreVCS(true)
            ->ignoreDotFiles(false)
            ->depth(0)
            ->directories()
            ->filter(function (\SplFileInfo $info): bool {
                return strpos($info->getBasename(), self::MIRROR_PREFIX) === 0;
            });

        foreach ($finder as $dir) {
            $filesystem->removeDirectory((string)$dir);
        }
    }

    /**
     * @return string
     */
    private function repoUrl(): string
    {
        $url = $this->remoteUrl ?: (string)($this->extra[Plugin::VIP_GIT_URL_KEY] ?? '');

        if (!$url
            || !filter_var($url, FILTER_VALIDATE_URL)
            || strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https'
            || strpos((string)parse_url($url, PHP_URL_HOST), 'github.com') === false
        ) {
            $this->io->writeError("<error>VIP: Git repo URL '{$url}' looks wrong.</error>");

            return '';
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!$path || $path === '/') {
            $this->io->writeError("<error>VIP: Git repo URL '{$url}' looks wrong.</error>");

            return '';
        }

        if (pathinfo(basename($url), PATHINFO_EXTENSION) !== 'git') {
            $url .= '.git';
            $path .= '.git';
        }

        $this->remoteUrl = $url;
        $this->sshRemoteUrl = 'git@github.com:' . ltrim($path, '/');

        return $this->sshRemoteUrl;
    }

    /**
     * @param Filesystem $filesystem
     * @return string
     */
    private function mirrorDir(Filesystem $filesystem): string
    {
        $mirror = uniqid(self::MIRROR_PREFIX, false);
        $mirrorPath = $this->directories->targetPath() . "/{$mirror}";
        $filesystem->ensureDirectoryExists($mirrorPath);

        $this->mirrorDir = $mirrorPath;

        return $this->mirrorDir;
    }

    /**
     * @param Filesystem $filesystem
     * @param InstalledPackages $packages
     * @return bool
     */
    private function fillMirror(Filesystem $filesystem, InstalledPackages $packages): bool
    {
        $copier = SafeCopier::create();
        $this->fillMirrorDirs($filesystem, $copier);

        $toCopy = $packages->noDevPackages();

        if (!$toCopy) {
            return true;
        }

        return $this->fillMirrorVendor($packages, $copier, $filesystem);
    }

    /**
     * @param Filesystem $filesystem
     * @param SafeCopier $copier
     */
    private function fillMirrorDirs(Filesystem $filesystem, SafeCopier $copier)
    {
        $paths = [
            $this->directories->languagesDir(),
            $this->directories->pluginsDir(),
            $this->directories->privateDir(),
            $this->directories->themesDir(),
            $this->directories->configDir(),
            $this->directories->imagesDir(),
        ];

        foreach ($paths as $source) {
            $target = "{$this->mirrorDir}/" . basename($source);
            $copier->copy($source, $target);
            $this->handleGitKeep($target);
        }

        $muSource = $this->directories->muPluginsDir();
        $vendorSource = $filesystem->normalizePath($this->config->get('vendor-dir'));

        $finder = Finder::create()
            ->in($muSource)
            ->ignoreVCS(true)
            ->ignoreDotFiles(true)
            ->depth(0);

        $muTarget = "{$this->mirrorDir}/" . basename($muSource) . '/';
        $filesystem->ensureDirectoryExists($muTarget);

        /** @var \SplFileInfo $info */
        foreach ($finder as $info) {
            $source = $filesystem->normalizePath((string)$info);
            if ($source === $vendorSource) {
                continue;
            }
            $target = $muTarget . $info->getBasename();
            $copier->copy($source, $target);
        }

        $this->handleGitKeep($muTarget);
    }

    /**
     * @param InstalledPackages $packages
     * @param SafeCopier $copier
     * @param Filesystem $filesystem
     * @return bool
     */
    private function fillMirrorVendor(
        InstalledPackages $packages,
        SafeCopier $copier,
        Filesystem $filesystem
    ): bool {

        $vendorSource = $filesystem->normalizePath($this->config->get('vendor-dir'));
        $targetPath = $this->directories->targetPath();
        $subdir = $filesystem->findShortestPath($targetPath, $vendorSource, true);

        $vendorTarget = $this->mirrorDir . "/{$subdir}";
        $filesystem->ensureDirectoryExists($vendorTarget);
        $toCopy = $packages->noDevPackages();

        if (!$toCopy) {
            $this->handleGitKeep($vendorTarget, true);

            return true;
        }

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

        $autoloadSource = "{$vendorSource}/" . AutoloadGenerator::PROD_AUTOLOAD_DIR;
        if (is_dir($autoloadSource)) {
            $all++;
            $autoloadTarget = "{$vendorTarget}/" . AutoloadGenerator::PROD_AUTOLOAD_DIR;
            $copier->copy($autoloadSource, $autoloadTarget) and $done++;
        }

        $this->handleGitKeep($vendorTarget, true);

        return $all === $done;
    }

    /**
     * @param string $dir
     * @param bool $isVendor
     */
    private function handleGitKeep(string $dir, bool $isVendor = false)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir, SCANDIR_SORT_NONE), ['.', '..']);
        $count = count($files);
        $hasKeep = in_array('.gitkeep', array_map('basename', $files), true);
        $keepPath = "{$dir}/.gitkeep";

        if (!$isVendor) {
            if (!$hasKeep) {
                file_put_contents($keepPath, '# Important: This directory cannot be empty.');
            }

            return;
        }

        if ($count > 1 && $hasKeep) {
            @unlink($keepPath);
            return;
        }

        if ($count === 0) {
            @touch($keepPath);
        }
    }

    /**
     * @param Filesystem $filesystem
     */
    private function wipeMirror(Filesystem $filesystem)
    {
        $finder = Finder::create()
            ->ignoreVCS(true)
            ->ignoreDotFiles(false)
            ->depth(0)
            ->in($this->mirrorDir);

        foreach ($finder as $path) {
            $filesystem->remove((string)$path);
        }
    }

    /**
     * @param string $target
     * @return bool
     */
    private function maybeCreateBranch(string $target): bool
    {
        list($success, $output) = $this->git->exec('branch -a');
        if (!$success) {
            return false;
        }

        $branches = $output ? array_filter(explode("\n", $output)) : [];

        $branchIsThere = false;
        $branchIsCurrent = false;
        $regex = "~(\* )?(?:(?:[a-z]+/)?origin/)?{$target}" . '$~';
        foreach ($branches as $branch) {
            if (preg_match($regex, trim($branch), $matches)) {
                $branchIsThere = true;
                $branchIsCurrent = ($matches[1] ?? '') === '* ';
            }
        }

        if ($branchIsThere) {
            $branchIsCurrent or $this->git->exec("checkout {$target}");

            return true;
        }

        $this->io->write("     <comment>Branch {$target} not on remote. Pushing...</comment>");

        list($success) = $this->git->exec("checkout -b {$target}");
        if (!$success) {
            return false;
        }

        list($success) = $this->git->exec("push -u origin {$target}");

        return $success;
    }

    /**
     * @param bool $push
     * @return mixed
     */
    private function mergeAndPush(bool $push): bool
    {
        list($success, $output) = $this->git->exec('branch');
        if (!$success) {
            $this->io->writeError('    <error>Failed reading branches.</error>');
            return false;
        }

        $targetBranch = $this->extra[Plugin::VIP_GIT_BRANCH_KEY] ?? 'development';

        $branches = explode($output, "\n");
        $currentBranch = '';
        foreach ($branches as $branch) {
            if (strpos(trim($branch), '* ')) {
                $currentBranch = ltrim($branch, '* ');
            }
        }

        $currentBranch === $targetBranch or $commands[] = "checkout {$targetBranch}";
        $commands[] = 'add .';
        $commands[] = 'commit -am "Merge-bot upstream sync."';

        list($success, , $outputs) = $this->git->exec(...$commands);
        $output = implode("\n", $outputs);

        $nothingToDo = strpos($output, 'up-to-date') !== false
            || strpos($output, 'working tree clean') !== false
            || strpos($output, 'nothing to commit') !== false;

        if (!$success) {
            $nothingToDo
                ? $this->nothingToDoMessage($push)
                : $this->io->writeError("    <error>{$output}</error>");

            return $nothingToDo;
        }

        if ($nothingToDo) {
            $this->nothingToDoMessage($push);

            return true;
        }

        $changes = $this->gitStats($push);
        if ($changes < 0 && $push) {
            $this->io->write('    <comment>Sorry, failed determining git status.</comment>');
            $push
                ? $this->io->write('    <comment>Will try to push anyway.</comment>')
                : $this->io->write("    <comment>Please check {$this->mirrorDir}.</comment>");
        }

        if (!$changes || !$push) {
            return true;
        }

        $this->io->write("    <comment>Pushing to <<<{$this->remoteUrl}>>>...</comment>");
        list($success) = $this->git->exec('push origin');

        return $success;
    }

    /**
     * @param bool $push
     * @return int
     */
    private function gitStats(bool $push): int
    {
        list($success, $output) = $this->git->exec('diff-tree --no-commit-id --name-status -r HEAD');
        if (!$success) {
            $push or $this->io->write('    <info>Changes merged but not pushed.</info>');

            return -1;
        }

        $files = array_filter(array_map('trim', explode("\n", $output)));
        if (!$files) {
            $push or $this->io->write('    <info>Changes merged but not pushed.</info>');

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
            function (string $file) use (&$counts) {
                $letter = strtoupper($file[0] ?? '');
                array_key_exists($letter, $counts) and $counts[$letter]++;
            }
        );

        if ($total < 1) {
            $this->nothingToDoMessage($push);

            return 0;
        }

        $messages[] = '    ' . str_repeat('_', 40);
        $push or $messages[] = '    <info>Changes merged but not pushed.</info>';
        $messages[] = "    Involved a total of {$total} files:";
        $messages[] = "     - <fg=green>{$counts['A']} added</>;";
        $messages[] = "     - <fg=cyan>{$counts['M']} modified</>;";
        $messages[] = "     - <fg=red>{$counts['D']} deleted</>.";
        $messages[] = "    The folder <info>{$this->mirrorDir}</info> is ready to be pushed.";
        $messages[] = '    ' . str_repeat('_', 40);

        $this->io->write($messages);

        return $total;
    }

    /**
     * @param bool $push
     */
    private function nothingToDoMessage(bool $push)
    {
        $this->io->write('     <info>Everything is already up-to-date!</info>');
        $push and $this->io->write('     <info>Nothing to push.</info>');
    }
}
