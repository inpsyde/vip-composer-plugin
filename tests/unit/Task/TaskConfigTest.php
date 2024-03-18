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
    public function testMissingGitBranch(): void
    {
        $config = new TaskConfig(
            [
                'git' => 'true',
                'git-url' => 'https://github.com/foo/bar/',
            ]
        );

        static::assertNull($config->gitBranch());
    }

    /**
     * @test
     * @dataProvider provideGitBranchNames
     *
     * @param mixed $name
     * @param bool $expected
     */
    public function testFailureOnInvalidGitBranch(mixed $name, bool $expected): void
    {
        $expected or $this->expectExceptionMessageMatches('/"git-branch"/');

        $config = new TaskConfig(
            [
                'git' => 'true',
                'git-branch' => $name,
                'git-url' => 'https://github.com/foo/bar.git',
            ]
        );

        if (!$expected) {
            return;
        }

        ($name === null)
            ? static::assertNull($config->gitBranch())
            : static::assertSame((string) $name, $config->gitBranch());
    }

    /**
     * @test
     */
    public function testMissingGitUrl(): void
    {
        $config = new TaskConfig(
            [
                'git' => 'true',
                'git-branch' => 'master',
            ]
        );

        static::assertNull($config->gitUrl());
    }

    /**
     * @test
     * @dataProvider provideGitUrls
     *
     * @param mixed $url
     * @param bool $expected
     */
    public function testFailureOnNoInvalidGitUrl(mixed $url, bool $expected): void
    {
        $expected or $this->expectExceptionMessageMatches('/"git-url"/');

        $config = new TaskConfig(
            [
                'git' => 'true',
                'git-branch' => 'master',
                'git-url' => $url,
            ]
        );

        if (!$expected) {
            return;
        }

        ($url === null)
            ? static::assertNull($config->gitUrl())
            : static::assertSame((string) $url, $config->gitUrl());
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
     * @return list<array{mixed, bool}>
     * @see https://git-scm.com/docs/git-check-ref-format
     */
    public static function provideGitBranchNames(): array
    {
        return [
            0 => ['foo/bar', true],
            1 => ['foo-bar', true],
            2 => ['', false],
            3 => [false, false], // filter will convert `false` to string "", failing
            4 => [4, true], // filter will convert int to string
            5 => [[], false],
            6 => [null, true],
            7 => [true, true], // filter will convert `true` to string "1"
            8 => ['x', true],
        ];
    }

    /**
     * @return list<array{mixed, bool}>
     */
    public static function provideGitUrls(): array
    {
        return [
            0 => ['https://github.com/foo/bar', true],
            1 => ['git@no-github.com:foo/bar', true],
            2 => ['', false],
            3 => [false, false],
            4 => [4, true],
            5 => [[], false],
            6 => [null, true],
            7 => [true, true],
        ];
    }
}
