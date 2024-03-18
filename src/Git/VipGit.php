<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Git;

use Composer\Util\Filesystem;
use Inpsyde\VipComposer\Config;
use Inpsyde\VipComposer\Task\TaskConfig;
use Inpsyde\VipComposer\Utils\InstalledPackages;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\Utils\Unzipper;
use Inpsyde\VipComposer\VipDirectories;
use Symfony\Component\Finder\Finder;

class VipGit
{
    private const MIRROR_PREFIX = '.vipgit';

    private array $gitConfig;
    private ?GitProcess $git = null;

    /**
     * @param Io $io
     * @param Config $config
     * @param VipDirectories $directories
     * @param InstalledPackages $packages
     * @param Filesystem $filesystem
     * @param Unzipper $unzipper
     */
    public function __construct(
        private Io $io,
        private Config $config,
        private VipDirectories $directories,
        private InstalledPackages $packages,
        private Filesystem $filesystem,
        private Unzipper $unzipper
    ) {

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
        $message = 'Starting Git sync';
        $push or $message .= ' (NO push will happen)';
        $this->io->commentLine("{$message}...");

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

        $url = $sshUrl ?: $httpsUrl;
        /** @var string|null $branch */
        $branch = $this->gitBranch($customBranch);
        if (!$branch) {
            return [null, null];
        }

        $git = $this->git;
        assert($git instanceof GitProcess);
        [$success] = $git->exec("clone {$url} .");

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
                    return str_starts_with($info->getBasename(), self::MIRROR_PREFIX);
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
     * @return void
     */
    private function fillMirror(string $mirrorDir): void
    {
        $copier = new MirrorCopier($this->io, $this->filesystem, $this->unzipper);
        $this->fillMirrorDirs($copier, $mirrorDir);

        $gitignore = new EnsureGitIgnore();
        $gitignore->ensure($mirrorDir);

        $toCopy = $this->packages->noDevPackages();

        if (!$toCopy) {
            return;
        }

        $this->fillMirrorVendor($copier, $mirrorDir);
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

        $git = $this->git;
        assert($git instanceof GitProcess);

        [$success, $output] = $git->exec('branch');
        if (!$success) {
            $this->io->errorLine('Failed reading branches.');

            return false;
        }

        $branches = explode("\n", $output);
        $currentBranch = '';
        foreach ($branches as $branch) {
            strpos(trim($branch), '* ') and $currentBranch = ltrim($branch, '* ');
        }

        $commands = [];
        ($currentBranch === $targetBranch) or $commands[] = "checkout {$targetBranch}";
        $commands[] = 'add .';
        $commands[] = 'commit -am "Merge-bot upstream sync."';

        [$success, , $outputs] = $git->exec(...$commands);
        $output = implode("\n", $outputs);

        $nothingToDo = str_contains($output, 'up-to-date')
            || str_contains($output, 'working tree clean')
            || str_contains($output, 'nothing to commit');

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
        [$success] = $git->exec('push origin');

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
            || !str_contains((string)parse_url($url, PHP_URL_HOST), 'github.com')
        ) {
            $this->io->errorLine("Git repo URL '{$url}' looks wrong.");

            return [null, null];
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!$path || $path === '/') {
            $this->io->errorLine("Git repo URL '{$url}' looks wrong.");

            return [null, null];
        }
        if (count(explode('/', trim($path, '/'))) !== 2) {
            $this->io->errorLine("Git repo URL path in '{$url}' looks wrong.");

            return [null, null];
        }

        if (pathinfo(basename($url), PATHINFO_EXTENSION) !== 'git') {
            $url .= '.git';
            $path .= '.git';
        }

        return [$url, 'git@github.com:' . ltrim($path, '/')];
    }

    /**
     * @param string|null $customBranch
     * @return string|null
     *
     * @see https://git-scm.com/docs/git-check-ref-format
     */
    private function gitBranch(?string $customBranch): ?string
    {
        $branch = $customBranch ?? $this->gitConfig[Config::GIT_BRANCH_KEY];
        $isCustom = $customBranch !== null;

        $error = $isCustom
            ? sprintf('Git branch name in "%s" command flag is invalid.', TaskConfig::GIT_BRANCH)
            : sprintf('Git branch name in "%s" configuration is invalid.', Config::GIT_BRANCH_KEY);

        if (!is_string($branch) || ($branch === '')) {
            if (!$isCustom) {
                $this->io->errorLine('Git branch not configured in composer.json.');
                $this->io->errorLine('Use `--git-branch` flag to pass a branch name.');

                return null;
            }

            $this->io->errorLine($error);

            return null;
        }

        $invalidChars = array_merge(
            range(chr(0), chr(40)),
            [chr(177), '\\', ' ', '~', '^', ':', '?', '*', '[']
        );

        if (in_array($branch, $invalidChars, true) || ($branch === '@')) {
            $this->io->errorLine($error);

            return null;
        }

        if ((trim($branch, '/') !== $branch) || (rtrim($branch, '.') !== $branch)) {
            $this->io->errorLine($error);

            return null;
        }

        $invalidChars = array_map('preg_quote', $invalidChars);
        if (preg_match('#' . implode('|', $invalidChars) . '|/\.|/{2,}|\.{2,}|@\{#', $branch)) {
            $this->io->errorLine($error);

            return null;
        }

        return $branch;
    }

    /**
     * @param string $targetBranch
     * @return bool
     */
    private function checkoutBranch(string $targetBranch): bool
    {
        $git = $this->git;
        assert($git instanceof GitProcess);

        [$success, $output] = $git->exec("branch -r -l *{$targetBranch}");
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
            $branchIsCurrent or $git->exec("checkout {$targetBranch}");

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
     * @return void
     */
    private function fillMirrorVendor(MirrorCopier $copier, string $mirrorDir): void
    {
        $vendorDir = $this->config->composerConfigValue('vendor-dir');
        $vendorSource = $this->filesystem->normalizePath($vendorDir);
        $targetPath = $this->directories->targetPath();
        $subDir = $this->filesystem->findShortestPath($targetPath, $vendorSource, true);
        $vendorTarget = "{$mirrorDir}/{$subDir}";
        $this->filesystem->ensureDirectoryExists($vendorTarget);
        $toCopy = $this->packages->noDevPackages();

        foreach ($toCopy as $package) {
            if (str_starts_with($package->getType(), 'wordpress-')) {
                continue;
            }
            $source = "{$vendorSource}/" . $package->getName();
            $target = "{$vendorTarget}/" . $package->getName();

            if (is_dir($source)) {
                $copier->copy($source, $target);
            }
        }

        $prodAutoloadDirname = $this->config->prodAutoloadDir();
        $autoloadSource = "{$vendorSource}/{$prodAutoloadDirname}";
        if (is_dir($autoloadSource)) {
            $autoloadTarget = "{$vendorTarget}/{$prodAutoloadDirname}";
            $copier->copy($autoloadSource, $autoloadTarget);
        }
    }

    /**
     * @param bool $push
     * @param string $mirrorDir
     * @return int
     */
    private function gitStats(bool $push, string $mirrorDir): int
    {
        $git = $this->git;
        assert($git instanceof GitProcess);

        [$success, $output] = $git->exec('diff-tree --no-commit-id --name-status -r HEAD');
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

        if (!$files) {
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
