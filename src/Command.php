<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the vip-composer-plugin package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.NoAccessors
 */

declare(strict_types=1);

namespace Inpsyde\VipComposer;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Command extends BaseCommand
{
    const REMOTE_URL = 'remote-url';
    const REMOTE_BRANCH = 'remote-branch';

    /**
     * @inheritdoc
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this
            ->setName('vip')
            ->setDescription('Run VIP installation workflow.')
            ->setDefinition(
                [
                    new InputOption(
                        'no-git',
                        null,
                        InputOption::VALUE_NONE,
                        'Do not sync to VIP git repo.'
                    ),
                    new InputOption(
                        'no-vip-mu',
                        null,
                        InputOption::VALUE_NONE,
                        'Do not download to VIP MU plugins.'
                    ),
                    new InputOption(
                        'push',
                        'p',
                        InputOption::VALUE_NONE,
                        'Push to remote.'
                    ),
                    new InputArgument(
                        'remote',
                        InputArgument::OPTIONAL,
                        'A different Git remote URL for VIP repo. Only relevant when --push is used.'
                    ),
                    new InputArgument(
                        'branch',
                        InputArgument::OPTIONAL,
                        'A different Git branch URL for VIP repo. Only relevant when --push is used.'
                    ),
                ]
            );
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $flags = $input->hasOption('no-git') && $input->getOption('no-git')
            ? Plugin::NO_GIT
            : Plugin::DO_GIT;

        if ($input->hasOption('push') && $input->getOption('push')) {
            $flags |= Plugin::DO_PUSH;
        }

        $config = [
            self::REMOTE_URL => $input->hasArgument('remote')
                ? $input->getArgument('remote')
                : null,
            self::REMOTE_BRANCH => $input->hasArgument('branch')
                ? $input->getArgument('branch')
                : null,
        ];

        $flags |= $input->hasOption('no-vip-mu') && $input->getOption('no-vip-mu')
                ? Plugin::NO_VIP_MU
                : Plugin::DO_VIP_MU;

        try {
            $plugin = Plugin::forCommand($flags, $config);
            $plugin->activate($this->getComposer(false, false), $this->getIO());
            $plugin->wpUpdate();
            $plugin->run();

            return 0;
        } catch (\Exception $exception) {
            $output->writeln($exception->getMessage());

            return 1;
        }
    }
}
