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

namespace Inpsyde\VipComposer\Tests\Task;

use Inpsyde\VipComposer\Task\TaskConfig;
use Inpsyde\VipComposer\Tests\UnitTestCase;

class TaskConfigTest extends UnitTestCase
{
    /**
     * @test
     */
    public function testDefaultsAreFilled(): void
    {
        $config = new TaskConfig(
            [
                'git' => 'true',
                'git-branch' => 'master',
                'git-url' => 'https://github.com/foo/bar',
            ]
        );

        static::assertSame('master', $config->gitBranch());
        static::assertSame('https://github.com/foo/bar', $config->gitUrl());
        static::assertTrue($config->isGit());
        static::assertTrue($config->isLocal());
        static::assertFalse($config->forceCoreUpdate());
        static::assertFalse($config->isDeploy());
    }

    /**
     * @test
     */
    public function testFailureOnMissingGitBranch(): void
    {
        $this->expectExceptionMessageMatches('/git-branch/');

        new TaskConfig(
            [
                'git' => 'true',
                'git-url' => 'https://github.com/foo/bar/',
            ]
        );
    }

    /**
     * @test
     * @dataProvider provideGitBranchNames
     */
    public function testFailureOnInvalidGitBranch(string $name, bool $expected): void
    {
        $expected or $this->expectExceptionMessageMatches('/"git-branch"/');

        $config = new TaskConfig(
            [
                'git' => 'true',
                'git-branch' => $name,
                'git-url' => 'https://github.com/foo/bar.git',
            ]
        );

        $expected and static::assertSame($name, $config->gitBranch());
    }

    /**
     * @test
     */
    public function testFailureOnMissingGitUrl(): void
    {
        $this->expectExceptionMessageMatches('/git-url/');

        new TaskConfig(
            [
                'git' => 'true',
                'git-branch' => 'master',
            ]
        );
    }

    /**
     * @test
     * @dataProvider provideGitUrls
     */
    public function testFailureOnNoInvalidGitUrl(string $url, bool $expected): void
    {
        $expected or $this->expectExceptionMessageMatches('/"git-url"/');

        $config = new TaskConfig(
            [
                'git' => 'true',
                'git-branch' => 'master',
                'git-url' => $url,
            ]
        );

        $expected and static::assertNotNull($config->gitUrl());
    }

    /**
     * @test
     */
    public function testFailureOninvalidBooleans(): void
    {
        $this->expectExceptionMessageMatches('/"git"/');

        new TaskConfig(
            [
                'git' => 'avaja',
                'git-branch' => 'master',
                'git-url' => 'https://github.com/foo/bar.git',
            ]
        );
    }

    /**
     * @return list<array{string, bool}>
     * @see https://git-scm.com/docs/git-check-ref-format
     */
    public static function provideGitBranchNames(): array
    {
        return [
            0 => ['foo/.bar', false],
            1 => ['foo..bar', false],
            2 => ['foo~bar', false],
            3 => ['foo^bar', false],
            4 => ['foo:bar', false],
            5 => ["foo\nbar", false],
            6 => ["foo\tbar", false],
            7 => ['foo?bar', false],
            8 => ['foo*bar', false],
            9 => ['foo[bar', false],
            10 => ['/foo', false],
            11 => ['foo/', false],
            12 => ['foo//bar', false],
            13 => ['foo.', false],
            14 => ['foo@{bar', false],
            15 => ['@', false],
            16 => ['foo\bar', false],
            17 => ['foo/bar', true],
            18 => ['foo.bar', true],
            19 => ['foo]bar', true],
            20 => ['foo/bar/baz', true],
            21 => ['foo{@bar', true],
            22 => ['x', true],
            23 => ['1', true],
            24 => ['123', true],
            25 => ['x/123', true],
            26 => ['1/x', true],
            27 => ['1/x/2', true],
            28 => ['1-2-z/y', true],
        ];
    }

    /**
     * @return list<array{string, bool}>
     */
    public static function provideGitUrls(): array
    {
        return [
            0 => ['https://github.com/foo/bar', true],
            1 => ['https://github.com/foo/bar/', true],
            2 => ['https://github.com/foo/bar.git', true],
            3 => ['https://no-github.com/foo/bar', false],
            4 => ['http://github.com/foo/bar', false],
            5 => ['https://github.com/foo', false],
            6 => ['https://github.com/foo.git', false],
            7 => ['git@github.com:foo/bar', true],
            8 => ['git@github.com:foo/bar/', true],
            9 => ['git@github.com:foo/bar.git', true],
            10 => ['git@no-github.com:foo/bar', false],
            11 => ['git@github.com:foo', false],
            12 => ['git@github.com:foo.git', false],
            13 => ['ssh@github.com:foo/bar', true],
            14 => ['ssh@github.com:foo/bar/', true],
            15 => ['ssh@github.com:foo/bar.git', true],
            16 => ['ssh@no-github.com:foo/bar', false],
            17 => ['ssh@github.com:foo', false],
            18 => ['ssh@github.com:foo.git', false],
        ];
    }
}
