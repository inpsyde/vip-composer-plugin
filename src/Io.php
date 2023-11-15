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

namespace Inpsyde\VipComposer;

use Composer\IO\IOInterface;

class Io
{
    public const ERROR = 'errorLine';
    public const COMMENT = 'commentLine';
    public const INFO = 'infoLine';
    public const VERBOSE_ERROR = 'verboseErrorLine';
    public const VERBOSE_COMMENT = 'verboseCommentLine';
    public const VERBOSE_INFO = 'verboseInfoLine';

    private const LINES_METHODS = [
        self::ERROR,
        self::COMMENT,
        self::INFO,
        self::VERBOSE_ERROR,
        self::VERBOSE_COMMENT,
        self::VERBOSE_INFO,
    ];

    /**
     * @param IOInterface $io
     */
    public function __construct(private IOInterface $io)
    {
    }

    /**
     * @return IOInterface
     */
    public function composerIo(): IOInterface
    {
        return $this->io;
    }

    /**
     * @param string $method
     * @param string[] $lines
     */
    public function lines(string $method, string ...$lines): void
    {
        if (in_array($method, self::LINES_METHODS, true)) {
            array_walk($lines, [$this, $method]);
        }
    }

    /**
     * @param string $message
     */
    public function error(string $message): void
    {
        $this->line("<error>$message</error>", true, IOInterface::NORMAL);
    }

    /**
     * @param string $message
     */
    public function comment(string $message): void
    {
        $this->line("<comment>$message</comment>", false, IOInterface::NORMAL);
    }

    /**
     * @param string $message
     */
    public function info(string $message): void
    {
        $this->line("<info>$message</info>", false, IOInterface::NORMAL);
    }

    /**
     * @param string $message
     */
    public function errorLine(string $message): void
    {
        $this->line("<error>      $message</error>", true, IOInterface::NORMAL);
    }

    /**
     * @param string $message
     */
    public function commentLine(string $message): void
    {
        $this->line("<comment>      $message</comment>", false, IOInterface::NORMAL);
    }

    /**
     * @param string $message
     */
    public function infoLine(string $message): void
    {
        $this->line("<info>      $message</info>", false, IOInterface::NORMAL);
    }

    /**
     * @param string $message
     */
    public function verboseError(string $message): void
    {
        $this->line("<error>$message</error>", true, IOInterface::VERBOSE);
    }

    /**
     * @param string $message
     */
    public function verboseComment(string $message): void
    {
        $this->line("<comment>$message</comment>", false, IOInterface::VERBOSE);
    }

    /**
     * @param string $message
     */
    public function verboseInfo(string $message): void
    {
        $this->line("<info>$message</info>", false, IOInterface::VERBOSE);
    }

    /**
     * @param string $message
     */
    public function verboseErrorLine(string $message): void
    {
        $this->line("<error>      $message</error>", true, IOInterface::VERBOSE);
    }

    /**
     * @param string $message
     */
    public function verboseCommentLine(string $message): void
    {
        $this->line("<comment>      $message</comment>", false, IOInterface::VERBOSE);
    }

    /**
     * @param string $message
     */
    public function verboseInfoLine(string $message): void
    {
        $this->line("<info>      $message</info>", false, IOInterface::VERBOSE);
    }

    /**
     * @param string $message
     */
    public function verboseLine(string $message): void
    {
        $this->line("      {$message}", false, IOInterface::VERBOSE);
    }

    /**
     * @param string $message
     * @param bool $error
     * @param int $verbosity
     */
    public function line(
        string $message,
        bool $error = false,
        int $verbosity = IOInterface::NORMAL
    ): void {

        $error
            ? $this->io->writeError($message, true, $verbosity)
            : $this->io->write($message, true, $verbosity);
    }
}
