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

use Composer\Util\Filesystem;
use Composer\Util\Platform;
use Composer\Util\ProcessExecutor;
use Inpsyde\VipComposer\Io;
use Symfony\Component\Process\ExecutableFinder;

class Unzipper
{
    /**
     * @var bool|null
     */
    private static ?bool $hasSystemUnzip = null;

    /**
     * @var bool|null
     */
    private static ?bool $hasZipArchive = null;

    /**
     * @param Io $io
     * @param ProcessExecutor $executor
     * @param Filesystem $filesystem
     */
    public function __construct(
        private Io $io,
        private ProcessExecutor $executor,
        private Filesystem $filesystem
    ) {
    }

    /**
     * Unzip a given zip file to given target path.
     *
     * @param string $zipFile
     * @param string $targetPath
     * @return bool
     *
     * @see unzipWithSystemZip
     * @see unzipWithZipArchive
     */
    public function unzip(string $zipFile, string $targetPath): bool
    {
        try {
            if (!is_file($zipFile) || !is_readable($zipFile)) {
                throw new \Exception("Can't unzip unreadable file {$zipFile}.");
            }

            [$hasSystemUnzip, $hasZipArchive] = $this->checkLibrary($zipFile);

            $this->filesystem->ensureDirectoryExists(dirname($targetPath));
            $this->filesystem->emptyDirectory($targetPath, true);

            /** @var array<callable(string, string):bool> $unzipCallbacks */
            $unzipCallbacks = [];
            $hasSystemUnzip and $unzipCallbacks[] = [$this, 'unzipWithSystemZip'];
            $hasZipArchive and $unzipCallbacks[] = [$this, 'unzipWithZipArchive'];
            if (Platform::isWindows() && count($unzipCallbacks) > 1) {
                $unzipCallbacks = array_reverse($unzipCallbacks);
            }

            return $this->attemptUnzip($zipFile, $targetPath, ...$unzipCallbacks);
        } catch (\Throwable $throwable) {
            $this->io->error('  ' . $throwable->getMessage());

            return false;
        }
    }

    /**
     * @param string $zipFile
     * @param string $targetPath
     * @param array<callable(string, string):bool> $attempts
     * @return bool
     */
    private function attemptUnzip(
        string $zipFile,
        string $targetPath,
        callable ...$attempts
    ): bool {

        $result = false;
        $count = 0;
        while (!$result && $attempts) {
            ($count > 0) and $this->filesystem->emptyDirectory($targetPath, true);
            $attempt = array_shift($attempts);
            $result = $attempt($zipFile, $targetPath);
            $count++;
        }

        return $result;
    }

    /**
     * @param string $zipFile
     * @param string $target
     * @return bool
     */
    private function unzipWithZipArchive(string $zipFile, string $target): bool
    {
        $zipArchive = new \ZipArchive();

        return $zipArchive->open($zipFile) === true && $zipArchive->extractTo($target);
    }

    /**
     * @param string $zipFile
     * @param string $target
     * @return bool
     */
    private function unzipWithSystemZip(string $zipFile, string $target): bool
    {
        $command = sprintf(
            'unzip -qq -o %s -d %s',
            ProcessExecutor::escape($zipFile),
            ProcessExecutor::escape($target)
        );

        try {
            $output = '';

            return $this->executor->execute($command, $output) !== 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check that system unzip command or ZipArchive class is available.
     *
     * @param string $zipPath
     * @return array{bool, bool}
     */
    private function checkLibrary(string $zipPath): array
    {
        if (isset(self::$hasSystemUnzip) && isset(self::$hasZipArchive)) {
            return [self::$hasSystemUnzip, self::$hasZipArchive];
        }

        self::$hasSystemUnzip = (bool)(new ExecutableFinder())->find('unzip');
        self::$hasZipArchive = class_exists('ZipArchive');

        if (!self::$hasSystemUnzip && !self::$hasZipArchive) {
            $name = basename($zipPath);
            throw new \RuntimeException(
                "Can't unzip '{$name}' because zip extension and unzip command are both missing."
            );
        }

        return [self::$hasSystemUnzip, self::$hasZipArchive];
    }
}
