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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Command extends BaseCommand
{
    const OPT_BRANCH = 'branch';
    const OPT_DEPLOY = 'deploy';
    const OPT_DO = 'do';
    const OPT_LOCAL_CREATE = 'local-create';
    const OPT_LOCAL_UPDATE = 'local-update';
    const OPT_NO_GIT = 'no-git';
    const OPT_NO_VIP_MU = 'no-vip-mu';
    const OPT_PUSH = 'push';
    const OPT_REMOTE = 'remote';

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
                        self::OPT_NO_GIT,
                        null,
                        InputOption::VALUE_NONE,
                        'Do not sync to VIP git repo.'
                    ),
                    new InputOption(
                        self::OPT_NO_VIP_MU,
                        null,
                        InputOption::VALUE_NONE,
                        'Do not download to VIP MU plugins.'
                    ),
                    new InputOption(
                        self::OPT_PUSH,
                        'p',
                        InputOption::VALUE_NONE,
                        'Push to remote.'
                    ),
                    new InputOption(
                        self::OPT_LOCAL_CREATE,
                        null,
                        InputOption::VALUE_NONE,
                        'Start local installation.'
                    ),
                    new InputOption(
                        self::OPT_LOCAL_UPDATE,
                        null,
                        InputOption::VALUE_NONE,
                        'Update local installation.'
                    ),
                    new InputOption(
                        self::OPT_DEPLOY,
                        null,
                        InputOption::VALUE_NONE,
                        'Alias for --no-vip-mu --push.'
                    ),
                    new InputOption(
                        self::OPT_DO,
                        null,
                        InputOption::VALUE_REQUIRED,
                        'Run a specific "DO" task.'
                    ),
                    new InputOption(
                        self::OPT_REMOTE,
                        null,
                        InputOption::VALUE_REQUIRED,
                        'A different Git remote URL for VIP repo. Only relevant when --push (or --deploy) is used.'
                    ),
                    new InputOption(
                        self::OPT_BRANCH,
                        null,
                        InputOption::VALUE_REQUIRED,
                        'A different Git branch URL for VIP repo. Only relevant when --push (or --deploy) is used.'
                    ),
                ]
            );
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        list($flags, $doWpUpdate) = $this->singleTaskFlags($input);
        ($flags === null) and $flags = $this->multiTaskFlags($input);

        try {
            $plugin = Plugin::forCommand($flags, $this->pushConfiguration($flags, $input));
            $plugin->activate($this->getComposer(false, false), $this->getIO());
            $doWpUpdate and $plugin->wpUpdate();
            $plugin->run();

            return 0;
        } catch (\Exception $exception) {
            $output->writeln($exception->getMessage());

            return 1;
        }
    }

    /**
     * @param InputInterface $input
     * @return array
     */
    private function singleTaskFlags(InputInterface $input): array
    {
        $flags = null;
        switch (true) {
            case ($input->hasOption(self::OPT_DEPLOY) && $input->getOption(self::OPT_DEPLOY)):
                $flags = Plugin::NO_VIP_MU | Plugin::DO_GIT | Plugin::DO_PUSH;
                break;
            case ($input->hasOption(self::OPT_LOCAL_CREATE) && $input->getOption(self::OPT_LOCAL_CREATE)):
                $flags = Plugin::LOCAL_CREATE;
                break;
            case ($input->hasOption(self::OPT_LOCAL_UPDATE) && $input->getOption(self::OPT_LOCAL_UPDATE)):
                $flags = Plugin::LOCAL_UPDATE;
                break;
            case ($input->hasOption(self::OPT_DO) && $input->getOption(self::OPT_DO)):
                $option = strtoupper((string)$input->getOption(self::OPT_DO));
                $taskDo = __NAMESPACE__ . "\\Plugin::DO_{$option}";
                $taskNo = __NAMESPACE__ . "\\Plugin::NO_{$option}";
                if (defined($taskDo) && defined($taskNo)) {
                    $flags = (Plugin::DO_NOTHING ^ constant($taskNo)) | constant($taskDo);
                }
                break;
        }

        return [$flags, $flags === null];
    }

    /**
     * @param InputInterface $input
     * @return int
     */
    private function multiTaskFlags(InputInterface $input): int
    {
        $flags = $input->hasOption(self::OPT_NO_GIT) && $input->getOption(self::OPT_NO_GIT)
            ? Plugin::NO_GIT
            : Plugin::DO_GIT;

        if ($input->hasOption(self::OPT_PUSH) && $input->getOption(self::OPT_PUSH)) {
            $flags |= Plugin::DO_PUSH;
        }

        $flags |= $input->hasOption(self::OPT_NO_VIP_MU) && $input->getOption(self::OPT_NO_VIP_MU)
            ? Plugin::NO_VIP_MU
            : Plugin::DO_VIP_MU;

        return $flags;
    }

    /**
     * @param int $flags
     * @param InputInterface $input
     * @return array
     */
    private function pushConfiguration(int $flags, InputInterface $input): array
    {
        if (($flags & Plugin::DO_PUSH) !== Plugin::DO_PUSH) {
            return [];
        }

        return [
            self::REMOTE_URL => $input->hasOption(self::OPT_REMOTE)
                ? $input->getOption(self::OPT_REMOTE)
                : null,
            self::REMOTE_BRANCH => $input->hasOption(self::OPT_BRANCH)
                ? $input->getOption(self::OPT_BRANCH)
                : null,
        ];
    }
}
