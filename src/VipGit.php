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
use Composer\Util\ProcessExecutor;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

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
     * @var string
     */
    private $targetPath;

    /**
     * @var array
     */
    private $extra;

    /**
     * @var callable
     */
    private $out;

    /**
     * @var array
     */
    private $captured = ['', ''];

    /**
     * @var ProcessExecutor
     */
    private $executor;

    /**
     * @var null|string
     */
    private $remoteUrl;

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
     * @param string $targetPath
     * @param array $extra
     * @param string|null $remoteGitUrl
     */
    public function __construct(
        IOInterface $io,
        Config $config,
        string $targetPath,
        array $extra,
        string $remoteGitUrl = null
    ) {

        $this->io = $io;
        $this->config = $config;
        $this->targetPath = $targetPath;
        $this->extra = $extra[Plugin::VIP_GIT_KEY] ?? [];
        $this->remoteUrl = $remoteGitUrl;
        $this->executor = new ProcessExecutor($io);
        $this->out = function (string $type = '', string $buffer = '') {
            $this->captured = [$type, $buffer];
        };
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

        if (!$this->mirrorDir($filesystem)) {
            $this->mirrorDir and $filesystem->removeDirectory($this->mirrorDir);
            return false;
        }

        list($success) = $this->git("clone {$url} .");

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
            ->in($this->targetPath)
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
        $this->remoteUrl = $url;

        if (!$url
            || !filter_var($url, FILTER_VALIDATE_URL)
            || strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https'
            || strpos((string)parse_url($url, PHP_URL_HOST), 'github.com') === false
        ) {
            $this->io->writeError("<error>VIP: Git repo URL '{$url}' looks wrong.</error>");

            return '';
        }

        if (pathinfo(basename($url), PATHINFO_EXTENSION) !== 'git') {
            $url .= '.git';
        }

        return $url;
    }

    /**
     * @param Filesystem $filesystem
     * @return string
     */
    private function mirrorDir(Filesystem $filesystem): string
    {
        $mirror = uniqid(self::MIRROR_PREFIX, false);
        $mirrorPath = "{$this->targetPath}/{$mirror}";
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
        $vendorSource = $filesystem->normalizePath($this->config->get('vendor-dir'));
        $vendorTarget = $this->fillMirrorDirs($vendorSource, $filesystem, $copier);
        if (!$vendorTarget) {
            return false;
        }

        $toCopy = $packages->noDevPackages();

        if (!$toCopy) {
            touch("{$vendorTarget}/.gitkeep");

            return true;
        }

        return $this->fillMirrorVendor($vendorSource, $vendorTarget, $packages, $copier);
    }

    /**
     * @param string $vendorSource
     * @param Filesystem $filesystem
     * @param SafeCopier $copier
     * @return string
     */
    private function fillMirrorDirs(
        string $vendorSource,
        Filesystem $filesystem,
        SafeCopier $copier
    ): string {

        $finder = Finder::create()
            ->in($this->targetPath)
            ->ignoreVCS(true)
            ->ignoreDotFiles(false)
            ->depth(0)
            ->filter(function (\SplFileInfo $info): bool {
                return strpos($info->getBasename(), self::MIRROR_PREFIX) !== 0;
            });

        $vendorTarget = '';

        /** @var \SplFileInfo $item */
        foreach ($finder as $item) {
            $source = $filesystem->normalizePath((string)$item);
            $target = "{$this->mirrorDir}/" . $item->getBasename();
            if ($source === $vendorSource && $item->isDir()) {
                $vendorTarget = $target;
                $filesystem->ensureDirectoryExists($vendorTarget);
                continue;
            }

            $copier->copy($source, $target);
            $this->handleGitKeep($target);
        }

        return $vendorTarget;
    }

    /**
     * @param string $vendorSource
     * @param string $vendorTarget
     * @param InstalledPackages $packages
     * @param SafeCopier $copier
     * @return bool
     */
    private function fillMirrorVendor(
        string $vendorSource,
        string $vendorTarget,
        InstalledPackages $packages,
        SafeCopier $copier
    ): bool {

        $toCopy = $packages->noDevPackages();

        if (!$toCopy) {
            $this->handleGitKeep($vendorTarget);

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

        $this->handleGitKeep($vendorTarget);

        return $all === $done;
    }

    /**
     * @param string $dir
     */
    private function handleGitKeep(string $dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir, SCANDIR_SORT_NONE), ['.', '..']);
        $count = count($files);
        $hasKeep = in_array('.gitkeep', array_map('basename', $files), true);
        if ($count > 1 && $hasKeep) {
            @unlink("{$dir}/.gitkeep");
            return;
        }

        if ($count === 0) {
            @touch("{$dir}/.gitkeep");
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
        list($success, $output) = $this->git('branch -a');
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
            $branchIsCurrent or $this->git("checkout {$target}");

            return true;
        }

        $this->io->write("     <comment>Branch {$target} not on remote. Pushing...</comment>");

        list($success) = $this->git("checkout -b {$target}");
        if (!$success) {
            return false;
        }

        list($success) = $this->git("push -u origin {$target}");

        return $success;
    }

    /**
     * @param bool $push
     * @return mixed
     */
    private function mergeAndPush(bool $push): bool
    {
        list($success, $output) = $this->git('branch');
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

        list($success,, $outputs) = $this->git(...$commands);
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
        list($success) = $this->git('push origin');

        return $success;
    }

    /**
     * @param bool $push
     * @return int
     */
    private function gitStats(bool $push): int
    {
        list($success, $output) = $this->git('diff-tree --no-commit-id --name-status -r HEAD');
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
                array_key_exists($letter, $counts) and $counts[$letter] ++;
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

    /**
     * @param string[] $commands
     * @return array
     */
    private function git(string ...$commands): array
    {
        if (!$this->mirrorDir || !is_dir($this->mirrorDir)) {
            throw new \RuntimeException('Error building directory for VIP git cloning.');
        }

        $outputs = [];
        $lastOutput = '';
        $code = 0;
        $vvv = $this->io->isVeryVerbose();

        while ($code === 0 && $commands) {
            $command = array_shift($commands);
            $vvv and $this->io->write("     <comment>Executing </comment>`git {$command}`");
            $code = $this->executor->execute("git {$command}", $this->out, $this->mirrorDir);
            list($type, $lastOutput) = $this->captured;
            $this->captured = ['', ''];
            $outputs[] = $lastOutput;
            if ($code !== 0 && $type === Process::ERR) {
                $this->io->writeError("<error>{$lastOutput}</error>");
            }
        }

        return [$code === 0, $lastOutput, $outputs];
    }
}
