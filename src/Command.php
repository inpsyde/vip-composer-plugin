<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer;

use Composer\Command\BaseCommand;
use Inpsyde\VipComposer\Task\TaskConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Command extends BaseCommand
{
    private const OPT_DEPLOY = 'deploy';
    private const OPT_FORCE_CORE_UPDATE = 'update-wp';
    private const OPT_FORCE_VIP_MU = 'update-vip-mu-plugins';
    private const OPT_GIT_BRANCH = 'branch';
    private const OPT_GIT_NO_PUSH = 'git';
    private const OPT_GIT_PUSH = 'push';
    private const OPT_GIT_URL = 'git-url';
    private const OPT_LOCAL = 'local';
    private const OPT_PROD_AUTOLOAD = 'prod-autoload';
    private const OPT_SKIP_CORE_UPDATE = 'skip-wp';
    private const OPT_SKIP_VIP_MU = 'skip-vip-mu-plugins';
    private const OPT_SYNC_DEV_PATHS = 'sync-dev-paths';
    private const OPT_VIP_DEV_ENV = 'vip-dev-env';

    private const OPTIONS = [
        self::OPT_DEPLOY => [TaskConfig::DEPLOY, false],
        self::OPT_FORCE_CORE_UPDATE => [TaskConfig::FORCE_CORE_UPDATE, false],
        self::OPT_FORCE_VIP_MU => [TaskConfig::FORCE_VIP_MU, false],
        self::OPT_GIT_BRANCH => [TaskConfig::GIT_BRANCH, null],
        self::OPT_GIT_NO_PUSH => [TaskConfig::GIT_NO_PUSH, false],
        self::OPT_GIT_PUSH => [TaskConfig::GIT_PUSH, false],
        self::OPT_GIT_URL => [TaskConfig::GIT_URL, null],
        self::OPT_LOCAL => [TaskConfig::LOCAL, true],
        self::OPT_PROD_AUTOLOAD => [TaskConfig::PROD_AUTOLOAD, false],
        self::OPT_SKIP_CORE_UPDATE => [TaskConfig::SKIP_CORE_UPDATE, false],
        self::OPT_SKIP_VIP_MU => [TaskConfig::SKIP_VIP_MU, false],
        self::OPT_SYNC_DEV_PATHS => [TaskConfig::SYNC_DEV_PATHS, false],
        self::OPT_VIP_DEV_ENV => [TaskConfig::VIP_DEV_ENV, false],
    ];

    /**
     * @return void
     *
     * phpcs:disable Inpsyde.CodeQuality.FunctionLength
     */
    protected function configure(): void
    {
        // phpcs:enable Inpsyde.CodeQuality.FunctionLength
        $this
            ->setName('vip')
            ->setDescription('Run VIP installation workflow.')
            ->setDefinition(
                [
                    new InputOption(
                        self::OPT_DEPLOY,
                        null,
                        InputOption::VALUE_NONE,
                        'Run script to deploy website.'
                    ),
                    new InputOption(
                        self::OPT_FORCE_CORE_UPDATE,
                        null,
                        InputOption::VALUE_NONE,
                        'Force the update of WordPress core. '
                        . 'To be used alone or in combination with --local.'
                        . 'Ignored if --deploy is used.'
                    ),
                    new InputOption(
                        self::OPT_FORCE_VIP_MU,
                        null,
                        InputOption::VALUE_NONE,
                        'Force the update of VIP Go MU plugins. Will take a while.'
                        . 'To be used in combination with --local. Ignored if --deploy is used.'
                    ),
                    new InputOption(
                        self::OPT_GIT_BRANCH,
                        null,
                        InputOption::VALUE_REQUIRED,
                        'A different Git branch for VIP repo. '
                        . 'When --local is used, this is relevant only if --git or --push '
                        . 'are used as well.'
                    ),
                    new InputOption(
                        self::OPT_GIT_NO_PUSH,
                        null,
                        InputOption::VALUE_NONE,
                        'Build Git mirror, but no push. To be used in combination with --local.'
                        . 'Ignored if --deploy is used.'
                    ),
                    new InputOption(
                        self::OPT_GIT_PUSH,
                        null,
                        InputOption::VALUE_NONE,
                        'Build Git mirror and push. To be used in combination with --local.'
                        . 'Ignored if --deploy is used.'
                    ),
                    new InputOption(
                        self::OPT_GIT_URL,
                        null,
                        InputOption::VALUE_REQUIRED,
                        'A different Git remote URL for VIP repo. '
                        . 'When --local is used, this is relevant only if --git or --push '
                        . 'are used as well.'
                    ),
                    new InputOption(
                        self::OPT_LOCAL,
                        null,
                        InputOption::VALUE_NONE,
                        'Run script for local installation.'
                    ),
                    new InputOption(
                        self::OPT_PROD_AUTOLOAD,
                        null,
                        InputOption::VALUE_NONE,
                        'Generate production autoload.'
                    ),
                    new InputOption(
                        self::OPT_SKIP_CORE_UPDATE,
                        null,
                        InputOption::VALUE_NONE,
                        'Skip the update of WordPress core. To be used in combination with --local.'
                        . 'Ignored if --deploy is used.'
                    ),
                    new InputOption(
                        self::OPT_SKIP_VIP_MU,
                        null,
                        InputOption::VALUE_NONE,
                        'Skip the update of VIP Go MU plugins. '
                        . 'To be used in combination with --local. '
                        . 'Ignored if --deploy is used.'
                    ),
                    new InputOption(
                        self::OPT_SYNC_DEV_PATHS,
                        null,
                        InputOption::VALUE_NONE,
                        'Synchronize local dev paths. To be used as only option.'
                    ),
                    new InputOption(
                        self::OPT_VIP_DEV_ENV,
                        null,
                        InputOption::VALUE_NONE,
                        'Prepare folder for VIP dev-env stack.'
                    ),
                ]
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->resetComposer();
            $composer = $this->requireComposer();

            $factory = new Factory($composer, $this->getIO());
            $config = $factory->config();

            if (!file_exists($config->composerLockPath())) {
                throw new \RuntimeException(
                    'Composer lock file not found. Please install via Composer first.'
                );
            }

            $taskFactory = new Task\Factory($factory, $this->createConfig($input));
            $taskFactory->tasks()
                ->addTask($taskFactory->downloadWpCore())
                ->addTask($taskFactory->downloadVipGoMuPlugins())
                ->addTask($taskFactory->symlinkVipGoDir())
                ->addTask($taskFactory->copyEnvConfig())
                ->addTask($taskFactory->copyDevPaths())
                ->addTask($taskFactory->generateMuPluginsLoader())
                ->addTask($taskFactory->generateProductionAutoload())
                ->addTask($taskFactory->updateLocalWpConfigFile())
                ->addTask($taskFactory->generateDeployVersion())
                ->addTask($taskFactory->ensureGitKeep())
                ->addTask($taskFactory->handleGit())
                ->run();
        } catch (\Throwable $exception) {
            $message = $exception->getMessage() . "\n\n" . $exception->getTraceAsString();
            $this->getIO()->writeError("<error>\n{$message}\n</error>");

            return 1;
        }

        return 0;
    }

    /**
     * @param InputInterface $input
     * @return Task\TaskConfig
     */
    private function createConfig(InputInterface $input): Task\TaskConfig
    {
        $options = [];
        foreach (self::OPTIONS as $key => [$taskKey, $default]) {
            $options[$taskKey] = $this->optionValue($input, $key, $default);
        }

        return new Task\TaskConfig($options);
    }

    /**
     * @param InputInterface $input
     * @param string $option
     * @param bool|null $default
     * @return mixed
     */
    private function optionValue(InputInterface $input, string $option, bool $default = null): mixed
    {
        if (!$input->hasOption($option)) {
            return $default;
        }

        return $input->getOption($option);
    }
}
