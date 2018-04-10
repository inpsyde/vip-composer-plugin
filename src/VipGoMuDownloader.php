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

/**
 * @author  Giuseppe Mazzapica <giuseppe.mazzapica@gmail.com>
 * @license http://opensource.org/licenses/MIT MIT
 */
class VipGoMuDownloader
{
    const GIT_URL = 'https://github.com/Automattic/vip-go-mu-plugins';

    private static $done = false;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var GitProcess
     */
    private $git;

    /**
     * @param IOInterface $io
     */
    public function __construct(IOInterface $io)
    {
        $this->io = $io;
    }

    public function download()
    {
        if (self::$done) {
            return;
        }

        self::$done = true;
        $filesystem = new Filesystem();
        $muTarget = $filesystem->normalizePath(getcwd() . '/mu-plugins');
        $cloneDir = 'vip-go-mu-plugins';
        $filesystem->ensureDirectoryExists($muTarget);
        $this->git = new GitProcess($this->io);
        list($code, $output) = $this->git->exec('clone ' . self::GIT_URL . " {$cloneDir}");
        if ($code !== 0) {
            $this->io->writeError('VIP: Failed cloning VIP GO MU plugins repository.');
            $this->io->writeError($output);

            return;
        }

        list($code, , $outputs) = $this->git
            ->cd($cloneDir)
            ->exec(
                'pull origin master',
                'submodule update --init --recursive'
            );

        if ($code !== 0) {
            $this->io->writeError('VIP: Failed pulling VIP GO MU plugins repository.');
            $this->io->writeError($outputs);

            return;
        }

        $filesystem->copyThenRemove(getcwd() . "/{$cloneDir}", $muTarget);
    }
}
