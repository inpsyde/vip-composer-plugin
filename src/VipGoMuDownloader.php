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

use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Composer\Util\ProcessExecutor;

/**
 * @author  Giuseppe Mazzapica <giuseppe.mazzapica@gmail.com>
 * @license http://opensource.org/licenses/MIT MIT
 */
class VipGoMuDownloader
{
    const GIT_URL = 'git@github.com:Automattic/vip-go-mu-plugins.git';

    private static $done = false;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var Directories
     */
    private $directories;

    /**
     * @var GitProcess
     */
    private $git;

    /**
     * @param IOInterface $io
     * @param Directories $directories
     */
    public function __construct(IOInterface $io, Directories $directories)
    {
        $this->io = $io;
        $this->directories = $directories;
    }

    public function download()
    {
        if (self::$done) {
            return;
        }

        self::$done = true;

        $targetDir = $this->directories->vipMuPluginsDir();
        if ($this->alreadyInstalled()) {
            $this->io->write('<info>VIP: VIP MU plugins already there.</info>');

            return;
        }

        $this->io->write('<info>VIP: Pulling VIP MU plugins...</info>');

        $filesystem = new Filesystem();
        $filesystem->emptyDirectory($targetDir, true);

        $timeout = ProcessExecutor::getTimeout();
        ProcessExecutor::setTimeout(0);
        $this->git = new GitProcess($this->io, $targetDir);
        list(, , $outputs) = $this->git->exec('clone  --recursive ' . self::GIT_URL . ' .');
        ProcessExecutor::setTimeout($timeout);

        if (!$this->alreadyInstalled()) {
            $this->io->writeError('<error>VIP: Failed cloning VIP GO MU plugins repository.</error>');
            $this->io->writeError($outputs);
        }
    }

    /**
     * @return bool
     */
    private function alreadyInstalled(): bool
    {
        $targetDir = $this->directories->vipMuPluginsDir();

        return is_dir($targetDir)
            && is_dir("{$targetDir}/vip-support")
            && file_exists("{$targetDir}/z-client-mu-plugins.php")
            && file_exists("{$targetDir}/vip-support/vip-support.php");
    }
}
