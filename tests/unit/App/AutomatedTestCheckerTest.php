<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Tests\App;

use Brain\Monkey;
use Inpsyde\VipComposer\Tests\UnitTestCase;
use Inpsyde\Vip\AutomatedTestChecker;

/**
 * @runTestsInSeparateProcesses
 */
class AutomatedTestCheckerTest extends UnitTestCase
{
    private array $globalsBackup = [];
    private ?string $secretHashed = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $path = getenv('VIP_COMPOSER_PLUGIN_LIBRARY_PATH');
        assert(is_string($path) && is_dir($path));
        require_once "{$path}/app/vip-config/__automated-test-checker.php";

        $this->globalsBackup = [$_REQUEST, $_SERVER, $_COOKIE];
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_NAME'] = 'example.com';
        $_SERVER['HTTP_HOST'] = 'www.example.com';
        define('COOKIE_DOMAIN', '');

        define('INPSYDE_AUTOTEST_KEY', 'test');
        $this->secretHashed = base64_encode(password_hash('test', \PASSWORD_DEFAULT));
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        [$_REQUEST, $_SERVER, $_COOKIE] = $this->globalsBackup;
        $this->secretHashed = null;
        parent::tearDown();
    }

    /**
     * @test
     */
    public function testDoNothingIfNoValue(): void
    {
        (new AutomatedTestChecker())->maybeSkip2fa();

        static::assertFalse(defined('WP_RUN_CORE_TESTS'));
    }

    /**
     * @test
     */
    public function testDoNothingIfCookieValueIsWrong(): void
    {
        $this->assertSetCookie(del: true);

        $_COOKIE[AutomatedTestChecker::COOKIE_KEY] = 'test';
        (new AutomatedTestChecker())->maybeSkip2fa();

        static::assertFalse(defined('WP_RUN_CORE_TESTS'));
    }

    /**
     * @test
     */
    public function testSetConstantIfCookieValueIsCorrect(): void
    {
        Monkey\Functions\expect('setcookie')->never();

        $_COOKIE[AutomatedTestChecker::COOKIE_KEY] = $this->secretHashed;
        (new AutomatedTestChecker())->maybeSkip2fa();

        static::assertTrue(\WP_RUN_CORE_TESTS);
    }

    /**
     * @test
     */
    public function testDoNothingIfCookieValueIsCorrectButProduction(): void
    {
        Monkey\Functions\expect('setcookie')->never();

        define('WP_ENVIRONMENT_TYPE', 'production');
        $_COOKIE[AutomatedTestChecker::COOKIE_KEY] = $this->secretHashed;
        (new AutomatedTestChecker())->maybeSkip2fa();

        static::assertFalse(defined('WP_RUN_CORE_TESTS'));
    }

    /**
     * @test
     */
    public function testSetConstantIfGlobalValueIsCorrect(): void
    {
        $this->assertSetCookie();

        $_REQUEST[strtoupper(AutomatedTestChecker::COOKIE_KEY)] = 'test';
        (new AutomatedTestChecker())->maybeSkip2fa();

        static::assertTrue(\WP_RUN_CORE_TESTS);
    }

    /**
     * @test
     */
    public function testSetConstantIfGlobalValueIsNotCorrect(): void
    {
        Monkey\Functions\expect('setcookie')->never();

        $_REQUEST[strtoupper(AutomatedTestChecker::COOKIE_KEY)] = $this->secretHashed;
        (new AutomatedTestChecker())->maybeSkip2fa();

        static::assertFalse(defined('WP_RUN_CORE_TESTS'));
    }

    /**
     * @test
     */
    public function testSetConstantIfHeadersValueIsCorrect(): void
    {
        $this->assertSetCookie();

        $key = 'HTTP_X_' . strtoupper(AutomatedTestChecker::COOKIE_KEY);
        $_SERVER[$key] = 'test';
        (new AutomatedTestChecker())->maybeSkip2fa();

        static::assertTrue(\WP_RUN_CORE_TESTS);
    }

    /**
     * @test
     */
    public function testSetCCookieForMultipleDomains(): void
    {
        $_SERVER['HTTP_HOST'] = 'www.example.it';
        $this->assertSetCookie(domains: ['.example.it', '.example.com']);

        $key = 'HTTP_X_' . strtoupper(AutomatedTestChecker::COOKIE_KEY);
        $_SERVER[$key] = 'test';
        (new AutomatedTestChecker())->maybeSkip2fa();

        static::assertTrue(\WP_RUN_CORE_TESTS);
    }

    /**
     * @test
     */
    public function testSetConstantIfHeadersValueIsNotCorrect(): void
    {
        Monkey\Functions\expect('setcookie')->never();

        $key = 'HTTP_X_' . strtoupper(AutomatedTestChecker::COOKIE_KEY);
        $_SERVER[$key] = $this->secretHashed;
        (new AutomatedTestChecker())->maybeSkip2fa();

        static::assertFalse(defined('WP_RUN_CORE_TESTS'));
    }

    /**
     * @param bool $del
     * @param array|null $domains
     * @return void
     */
    private function assertSetCookie(bool $del = false, ?array $domains = null): void
    {
        $domains ??= ['.example.com'];

        Monkey\Functions\expect('setcookie')
            ->times(count($domains))
            ->andReturnUsing(
                static function (string $key, string $val, array $args) use ($del, $domains): bool {

                    static::assertSame(AutomatedTestChecker::COOKIE_KEY, $key);
                    static::assertSame('/', $args['path'] ?? null);
                    static::assertTrue($args['httponly'] ?? null);
                    static::assertTrue($args['secure'] ?? null);
                    static::assertSame('Lax', $args['samesite'] ?? null);
                    static::assertTrue(in_array($args['domain'] ?? null, $domains, true));
                    if (!$del) {
                        static::assertSame(0, $args['expires'] ?? null);
                        static::assertTrue(password_verify('test', base64_decode($val)));
                        return true;
                    }
                    static::assertSame('', $val);
                    static::assertLessThan(time() - 1000, $args['expires'] ?? null);
                    return false;
                }
            );
    }
}
