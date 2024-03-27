<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Tests\App;

use Brain\Monkey;
use Inpsyde\VipComposer\Tests\UnitTestCase;
use Inpsyde\Vip;

/**
 * @runTestsInSeparateProcesses
 */
class HelpersTest extends UnitTestCase
{
    private array $globalsBackup = [];

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $path = getenv('VIP_COMPOSER_PLUGIN_LIBRARY_PATH');
        assert(is_string($path) && is_dir($path));
        require_once "{$path}/app/vip-config/__helpers.php";
        $this->globalsBackup = [$_GET, $_SERVER];
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        [$_GET, $_SERVER] = $this->globalsBackup;
        parent::tearDown();
    }

    /**
     * @test
     */
    public function testDetermineVipEnvNoConstant(): void
    {
        static::assertSame('local', Vip\determineVipEnv());
        define('VIP_GO_ENV', 'preprod');
        static::assertSame('local', Vip\determineVipEnv());
    }

    /**
     * @test
     */
    public function testDetermineVipEnvConstant(): void
    {
        define('VIP_GO_ENV', 'preprod');
        static::assertSame('preprod', Vip\determineVipEnv());
    }

    /**
     * @test
     * @dataProvider provideWpEnv
     */
    public function testDetermineWpEnv(string $vipEnv, string $expectedWpEnv): void
    {
        define('VIP_GO_ENV', $vipEnv);
        static::assertSame($expectedWpEnv, Vip\determineWpEnv());
    }

    /**
     * @test
     * @dataProvider provideWpEnv
     */
    public function testDetermineWpEnvByConstant(string $vipEnv): void
    {
        define('VIP_GO_ENV', $vipEnv);
        define('WP_ENVIRONMENT_TYPE', 'development');
        static::assertSame('development', Vip\determineWpEnv());
    }

    /**
     * @return \Generator
     */
    public static function provideWpEnv(): \Generator
    {
        yield from [
            ['local', 'local'],
            ['local-test', 'local'],
            ['dev', 'development'],
            ['development2', 'development'],
            ['stage', 'staging'],
            ['preprod', 'staging'],
            ['training', 'staging'],
            ['uat', 'staging'],
            ['prod', 'production'],
            ['prod', 'production'],
        ];
    }

    /**
     * @test
     * @dataProvider provideBuildFullRedirectUrlFor
     */
    public function testBuildFullRedirectUrlFor(
        string $input,
        bool $preservePath,
        bool $preserveQuery,
        ?string $expectedOutput
    ): void {

        $_GET = ['var' => 'a & b ? +='];
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['HTTPS'] = 'on';

        $output = Vip\buildFullRedirectUrlFor($input, $preservePath, $preserveQuery);

        static::assertSame($expectedOutput, $output);
    }

    /**
     * @return \Generator
     */
    public function provideBuildFullRedirectUrlFor(): \Generator
    {
        return yield from [
            ['example.com', true, true, 'https://example.com/test?var=a+%26+b+%3F+%2B%3D'],
            ['example.com', true, false, 'https://example.com/test'],
            ['example.com', false, false, 'https://example.com'],
            ['example.com', false, true, 'https://example.com?var=a+%26+b+%3F+%2B%3D'],
            ['example.com/es', false, true, 'https://example.com/es?var=a+%26+b+%3F+%2B%3D'],
            ['example.com/es', true, true, 'https://example.com/es/test?var=a+%26+b+%3F+%2B%3D'],
            ['example.com?x=y', false, true, 'https://example.com?var=a+%26+b+%3F+%2B%3D&x=y'],
        ];
    }

    /**
     * @test
     */
    public function testDeployIdFileNotExists(): void
    {
        static::assertNull(Vip\deployIdFile());
        define(
            'WPCOM_VIP_PRIVATE_DIR',
            ((string) getenv('VIP_COMPOSER_PLUGIN_TESTS_BASE_PATH')) . '/fixtures/private'
        );
        static::assertNull(Vip\deployIdFile());
    }

    /**
     * @test
     */
    public function testDeployIdFileExists(): void
    {
        $dir = ((string) getenv('VIP_COMPOSER_PLUGIN_TESTS_BASE_PATH')) . '/fixtures/private';
        define('WPCOM_VIP_PRIVATE_DIR', $dir);
        static::assertSame("{$dir}/deploy-id", Vip\deployIdFile());
    }

    /**
     * @test
     */
    public function testDeployIdNotExistsLocal(): void
    {
        $id = Vip\deployId();
        static::assertTrue(is_string($id));
        static::assertSame(16, strlen($id));
        static::assertSame($id, Vip\deployId());
    }

    /**
     * @test
     */
    public function testDeployIdNotExistsProduction(): void
    {
        define('WP_ENVIRONMENT_TYPE', 'production');
        static::assertNull(Vip\deployId());
    }

    /**
     * @test
     */
    public function testDeployIdExists(): void
    {
        $dir = ((string) getenv('VIP_COMPOSER_PLUGIN_TESTS_BASE_PATH')) . '/fixtures/private';
        define('WPCOM_VIP_PRIVATE_DIR', $dir);
        static::assertSame('c59a7d8c-a867-486a-8ccf-76f5d26bee46', Vip\deployId());
    }

    /**
     * @test
     */
    public function testDeployVerNotExistsLocal(): void
    {
        static::assertNull(Vip\deployVersion());
    }

    /**
     * @test
     */
    public function testDeployVerNotExistsProduction(): void
    {
        define('WP_ENVIRONMENT_TYPE', 'production');
        static::assertNull(Vip\deployVersion());
    }

    /**
     * @test
     */
    public function testDeployVerExists(): void
    {
        $dir = ((string) getenv('VIP_COMPOSER_PLUGIN_TESTS_BASE_PATH')) . '/fixtures/private';
        define('WPCOM_VIP_PRIVATE_DIR', $dir);
        static::assertSame('1.2.3', Vip\deployVersion());
    }

    /**
     * @test
     */
    public function testSunriseConfigJsonLocalEnv(): void
    {
        $dir = ((string) getenv('VIP_COMPOSER_PLUGIN_TESTS_BASE_PATH')) . '/fixtures/json';
        Monkey\Functions\when('Inpsyde\\Vip\\vipConfigPath')->justReturn($dir);

        static::assertSame(
            [
                'target' => 'example.com/es',
                'redirect' => true,
                'preservePath' => true,
                'preserveQuery' => true,
                'status' => 301,
            ],
            Vip\loadSunriseConfigForDomain('example.com')
        );

        static::assertSame(
            [
                'target' => 'acme.com',
                'redirect' => true,
                'preservePath' => false,
                'preserveQuery' => true,
                'status' => 301,
            ],
            Vip\loadSunriseConfigForDomain('www.acme.com')
        );

        static::assertSame(
            [
                'target' => 'main-domain.com',
                'redirect' => false,
                'preservePath' => false,
                'preserveQuery' => false,
                'status' => 301,
            ],
            Vip\loadSunriseConfigForDomain('alternative-domain.com')
        );

        static::assertSame(
            [
                'target' => '',
                'redirect' => false,
                'preservePath' => false,
                'preserveQuery' => false,
                'status' => 301,
            ],
            Vip\loadSunriseConfigForDomain('www.production.example.com')
        );
    }

    /**
     * @test
     */
    public function testSunriseConfigJsonProdEnv(): void
    {
        define('WP_ENVIRONMENT_TYPE', 'production');
        $dir = ((string) getenv('VIP_COMPOSER_PLUGIN_TESTS_BASE_PATH')) . '/fixtures/json';
        Monkey\Functions\when('Inpsyde\\Vip\\vipConfigPath')->justReturn($dir);

        static::assertSame(
            [
                'target' => 'example.com/production',
                'redirect' => true,
                'preservePath' => true,
                'preserveQuery' => true,
                'status' => 301,
            ],
            Vip\loadSunriseConfigForDomain('example.com')
        );

        static::assertSame(
            [
                'target' => 'acme.com',
                'redirect' => true,
                'preservePath' => true,
                'preserveQuery' => true,
                'status' => 302,
            ],
            Vip\loadSunriseConfigForDomain('www.acme.com')
        );

        static::assertSame(
            [
                'target' => 'main-domain.com',
                'redirect' => false,
                'preservePath' => false,
                'preserveQuery' => false,
                'status' => 301,
            ],
            Vip\loadSunriseConfigForDomain('alternative-domain.com')
        );

        static::assertSame(
            [
                'target' => 'production.example.com',
                'redirect' => true,
                'preservePath' => true,
                'preserveQuery' => true,
                'status' => 301,
            ],
            Vip\loadSunriseConfigForDomain('www.production.example.com')
        );
    }

    /**
     * @test
     */
    public function testSunriseConfigPhpLocalEnv(): void
    {
        $dir = ((string) getenv('VIP_COMPOSER_PLUGIN_TESTS_BASE_PATH')) . '/fixtures/php';
        Monkey\Functions\when('Inpsyde\\Vip\\vipConfigPath')->justReturn($dir);

        static::assertSame(
            [
                'target' => 'example.com/es',
                'redirect' => true,
                'preservePath' => true,
                'preserveQuery' => true,
                'status' => 301,
            ],
            Vip\loadSunriseConfigForDomain('example.com')
        );

        static::assertSame(
            [
                'target' => 'acme.com',
                'redirect' => true,
                'preservePath' => false,
                'preserveQuery' => true,
                'status' => 301,
            ],
            Vip\loadSunriseConfigForDomain('www.acme.com')
        );

        static::assertSame(
            [
                'target' => 'main-domain.com',
                'redirect' => false,
                'preservePath' => false,
                'preserveQuery' => false,
                'status' => 301,
            ],
            Vip\loadSunriseConfigForDomain('alternative-domain.com')
        );

        static::assertSame(
            [
                'target' => '',
                'redirect' => false,
                'preservePath' => false,
                'preserveQuery' => false,
                'status' => 301,
            ],
            Vip\loadSunriseConfigForDomain('www.production.example.com')
        );
    }

    /**
     * @test
     */
    public function testSunriseConfigPhpProdEnv(): void
    {
        define('WP_ENVIRONMENT_TYPE', 'production');
        $dir = ((string) getenv('VIP_COMPOSER_PLUGIN_TESTS_BASE_PATH')) . '/fixtures/php';
        Monkey\Functions\when('Inpsyde\\Vip\\vipConfigPath')->justReturn($dir);

        static::assertSame(
            [
                'target' => 'example.com/production',
                'redirect' => true,
                'preservePath' => true,
                'preserveQuery' => true,
                'status' => 301,
            ],
            Vip\loadSunriseConfigForDomain('example.com')
        );

        static::assertSame(
            [
                'target' => 'acme.com',
                'redirect' => true,
                'preservePath' => true,
                'preserveQuery' => true,
                'status' => 302,
            ],
            Vip\loadSunriseConfigForDomain('www.acme.com')
        );

        static::assertSame(
            [
                'target' => 'main-domain.com',
                'redirect' => false,
                'preservePath' => false,
                'preserveQuery' => false,
                'status' => 301,
            ],
            Vip\loadSunriseConfigForDomain('alternative-domain.com')
        );

        static::assertSame(
            [
                'target' => 'production.example.com',
                'redirect' => true,
                'preservePath' => true,
                'preserveQuery' => true,
                'status' => 301,
            ],
            Vip\loadSunriseConfigForDomain('www.production.example.com')
        );
    }

    /**
     * @test
     */
    public function testLoadConfigFilesWeirdEnvName(): void
    {
        define('VIP_GO_APP_ENVIRONMENT', 'weird');

        $dir = ((string) getenv('VIP_COMPOSER_PLUGIN_TESTS_BASE_PATH')) . '/fixtures/env1';
        Monkey\Functions\when('Inpsyde\\Vip\\vipConfigPath')->justReturn($dir);
        Vip\loadConfigFiles();

        static::assertSame('WEIRD', Vip\WEIRD);
        static::assertFalse(defined('Inpsyde\\Vip\\LOCAL'));
        static::assertFalse(defined('Inpsyde\\Vip\\DEVELOPMENT'));
        static::assertFalse(defined('Inpsyde\\Vip\\ALL'));

        static::assertSame('weird', Vip\determineVipEnv());
        static::assertSame('staging', Vip\determineWpEnv());
    }

    /**
     * @test
     */
    public function testLoadConfigFilesMappedEnvName(): void
    {
        define('VIP_GO_APP_ENVIRONMENT', 'dev-temp');

        $dir = ((string) getenv('VIP_COMPOSER_PLUGIN_TESTS_BASE_PATH')) . '/fixtures/env1';
        Monkey\Functions\when('Inpsyde\\Vip\\vipConfigPath')->justReturn($dir);
        Vip\loadConfigFiles();

        static::assertSame('DEVELOPMENT', Vip\DEVELOPMENT);
        static::assertFalse(defined('Inpsyde\\Vip\\WEIRD'));
        static::assertFalse(defined('Inpsyde\\Vip\\LOCAL'));
        static::assertFalse(defined('Inpsyde\\Vip\\ALL'));

        static::assertSame('dev-temp', Vip\determineVipEnv());
        static::assertSame('development', Vip\determineWpEnv());
    }

    /**
     * @test
     */
    public function testLoadConfigFilesMappedEnvNameNotMapped(): void
    {
        define('VIP_GO_APP_ENVIRONMENT', 'dev-temp');
        define('Inpsyde\\Vip\\CONFIG_FILES_ENV_FALLBACK', false);

        $dir = ((string) getenv('VIP_COMPOSER_PLUGIN_TESTS_BASE_PATH')) . '/fixtures/env1';
        Monkey\Functions\when('Inpsyde\\Vip\\vipConfigPath')->justReturn($dir);
        Vip\loadConfigFiles();

        static::assertFalse(defined('Inpsyde\\Vip\\DEVELOPMENT'));
        static::assertFalse(defined('Inpsyde\\Vip\\WEIRD'));
        static::assertFalse(defined('Inpsyde\\Vip\\LOCAL'));
        static::assertFalse(defined('Inpsyde\\Vip\\ALL'));

        static::assertSame('dev-temp', Vip\determineVipEnv());
        static::assertSame('development', Vip\determineWpEnv());
    }

    /**
     * @test
     */
    public function testLoadConfigFilesLocalFoundFile(): void
    {
        $dir = ((string) getenv('VIP_COMPOSER_PLUGIN_TESTS_BASE_PATH')) . '/fixtures/env1';
        Monkey\Functions\when('Inpsyde\\Vip\\vipConfigPath')->justReturn($dir);
        Vip\loadConfigFiles();

        static::assertSame('LOCAL', Vip\LOCAL);
        static::assertFalse(defined('Inpsyde\\Vip\\WEIRD'));
        static::assertFalse(defined('Inpsyde\\Vip\\DEVELOPMENT'));
        static::assertFalse(defined('Inpsyde\\Vip\\ALL'));

        static::assertSame('local', Vip\determineVipEnv());
        static::assertSame('local', Vip\determineWpEnv());
    }

    /**
     * @test
     */
    public function testLoadConfigFilesLocalNotFoundFileFallbackToDevelopment(): void
    {
        $dir = ((string) getenv('VIP_COMPOSER_PLUGIN_TESTS_BASE_PATH')) . '/fixtures/env2';
        Monkey\Functions\when('Inpsyde\\Vip\\vipConfigPath')->justReturn($dir);
        Vip\loadConfigFiles();

        static::assertSame('DEVELOP', Vip\DEVELOP);
        static::assertSame('ALL', Vip\ALL);
        static::assertFalse(defined('Inpsyde\\Vip\\LOCAL'));
        static::assertFalse(defined('Inpsyde\\Vip\\WEIRD'));
        static::assertFalse(defined('Inpsyde\\Vip\\DEVELOPMENT'));

        static::assertSame('local', Vip\determineVipEnv());
        static::assertSame('local', Vip\determineWpEnv());
    }

    /**
     * @test
     */
    public function testLoadConfigFilesLocalNotFoundFileFallbackDisabled(): void
    {
        define('Inpsyde\\Vip\\CONFIG_FILES_LOCAL_FALLBACK', false);

        $dir = ((string) getenv('VIP_COMPOSER_PLUGIN_TESTS_BASE_PATH')) . '/fixtures/env2';
        Monkey\Functions\when('Inpsyde\\Vip\\vipConfigPath')->justReturn($dir);
        Vip\loadConfigFiles();

        static::assertSame('ALL', Vip\ALL);
        static::assertFalse(defined('Inpsyde\\Vip\\DEVELOP'));
        static::assertFalse(defined('Inpsyde\\Vip\\LOCAL'));
        static::assertFalse(defined('Inpsyde\\Vip\\WEIRD'));
        static::assertFalse(defined('Inpsyde\\Vip\\DEVELOPMENT'));

        static::assertSame('local', Vip\determineVipEnv());
        static::assertSame('local', Vip\determineWpEnv());
    }
}
