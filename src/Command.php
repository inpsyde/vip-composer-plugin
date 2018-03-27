<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the vip-composer-plugin package.
 *
 * (c) © 2018 UEFA. All rights reserved.
 */

declare(strict_types=1);

namespace Uefa\VipComposer;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Command extends BaseCommand
{
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

        $url = $input->hasArgument('remote') ? $input->getArgument('remote') : null;

        try {
            $plugin = Plugin::forCommand($flags, $url);
            $plugin->activate($this->getComposer(false, false), $this->getIO());
            $plugin->wpDownloaderUpdate();
            $plugin->run();

            return 0;
        } catch (\Exception $exception) {
            $output->writeln($exception->getMessage());

            return 1;
        }
    }
}
