<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the vip-composer-plugin package.
 *
 * (c) © 2018 UEFA. All rights reserved.
 */

declare(strict_types=1);

namespace Uefa\VipComposer;

use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Composer\Util\ProcessExecutor;
use Symfony\Component\Process\Process;

class VipGit
{

    /**
     * @var string
     */
    private $targetPath;

    /**
     * @var IOInterface
     */
    private $io;
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
     * @var string
     */
    private $https;

    /**
     * @var string
     */
    private $ssh;

    /**
     * @var null|string
     */
    private $remoteUrl;

    private $doPush = false;

    /**
     * @param IOInterface $io
     * @param string $targetPath
     * @param array $extra
     * @param string|null $remoteGitUrl
     */
    public function __construct(
        IOInterface $io,
        string $targetPath,
        array $extra,
        string $remoteGitUrl = null
    ) {

        $this->io = $io;
        $this->targetPath = $targetPath;
        $this->extra = $extra[Plugin::VIP_GIT_KEY] ?? [];
        $this->remoteUrl = $remoteGitUrl;
        $this->executor = new ProcessExecutor($io);
        $this->out = function ($type = '', $buffer = '') {
            $this->captured = [$type, $buffer];
        };
    }

    /**
     * @param Filesystem $filesystem
     * @return bool
     */
    public function init(Filesystem $filesystem): bool
    {
        $url = $this->repoUrl();
        if (!$url) {
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
     * @return bool
     */
    public function sync(Filesystem $filesystem): bool
    {
        $push = $this->doPush;
        $this->doPush = false;

        if (!is_dir("{$this->targetPath}/.git")) {
            $this->io->writeError("<error>VIP: {$this->targetPath} is not a Git repo.</error>");

            return false;
        }

        $changesBranch = 'auto-sync-' . date('YmdHis');

        if (!$this->commitCurrentChanges($changesBranch)) {
            return false;
        }

        $this->io->write('     <comment>Changes committed.</comment>');
        $this->io->write('     <comment>Merging started...</comment>');

        $success = $this->mergeAndPush($changesBranch, $push);
        if ($success) {
            $this->io->write('<info>VIP: done.</info>');
            $push and $filesystem->removeDirectory("{$this->targetPath}/.git");
        }

        return $success;
    }

    /**
     * @param Filesystem $filesystem
     * @return bool
     */
    public function push(Filesystem $filesystem): bool
    {
        $this->doPush = true;

        return $this->sync($filesystem);
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

        if (pathinfo(basename($url), PATHINFO_EXTENSION) !== 'git') {
            $url .= '.git';
        }

        $parts = explode('github.com/', $url);
        $path = array_pop($parts);

        $this->https = $url;
        $this->ssh = "git@github.com:{$path}";

        return $url;
    }

    /**
     * @param string $targetBranch
     * @return bool
     */
    private function maybeCreateBranch(string $targetBranch)
    {
        list($success, $output) = $this->git('branch');
        if (!$success) {
            return false;
        }

        $branches = $output ? explode("\n", $output) : [];
        $localIsThere = false;
        $localIsCurrent = false;
        foreach ($branches as $branch) {
            $branch = trim($branch);
            if (ltrim($branch, '* ') === $targetBranch) {
                $localIsThere = true;
                $localIsCurrent = strpos($branch, '* ') === 0;
            }
        }

        if ($localIsThere) {
            $this->io->write("     <comment>Branch {$targetBranch} exists already.</comment>");
            $localIsCurrent or $this->git("checkout {$targetBranch}");

            return true;
        }

        list($success) = $this->git("checkout -b {$targetBranch}");
        if (!$success) {
            return false;
        }

        $this->io->write("     <comment>Branch {$targetBranch} not on remote. Pushing...</comment>");
        list($success) = $this->git("push -u origin {$targetBranch}");

        return $success;
    }

    /**
     * @param string $changesBranch
     * @return bool
     */
    private function commitCurrentChanges(string $changesBranch): bool
    {
        $commands[] = "checkout -b {$changesBranch}";
        $commands[] = 'add .';
        $commands[] = 'commit -m "Auto-sync from Inpsyde merge bot."';

        list($success) = $this->git(...$commands);

        return $success;
    }

    /**
     * @param string $changesBranch
     * @param bool $push
     * @return mixed
     */
    private function mergeAndPush(string $changesBranch, bool $push): bool
    {
        list($success, $output) = $this->git('branch');
        if (!$success) {
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

        $commands = ['fetch origin'];
        $currentBranch === $changesBranch or $commands[] = "checkout {$changesBranch}";
        $commands[] = "rebase {$targetBranch}";
        $commands[] = "checkout {$targetBranch}";
        $commands[] = "merge {$changesBranch}";
        $push and $commands[] = 'push origin';

        list($success) = $this->git(...$commands);

        return $success;
    }

    /**
     * @param string[] $commands
     * @return array
     */
    private function git(string ...$commands): array
    {
        $outputs = [];
        $lastOutput = '';
        $code = 0;
        $vvv = $this->io->isVeryVerbose() || 1;
        while ($code === 0 && $commands) {
            $command = array_shift($commands);
            $vvv and $this->io->write("     <comment>Executing 'git {$command}'...</comment>");
            $code = $this->executor->execute("git {$command}", $this->out, $this->targetPath);
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
