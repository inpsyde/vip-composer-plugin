<?php

/*
 * This file is part of the vip-composer-plugin package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Inpsyde\VipComposer\Tests\Task;

use Inpsyde\VipComposer\Task\DownloadWpCore;
use Inpsyde\VipComposer\Tests\UnitTestCase;

class DownloadWpCoreTest extends UnitTestCase
{
    /**
     * @test
     * @dataProvider provideWpVersions
     */
    public function testNormalizeWpVersion(string $input, string $expected): void
    {
        static::assertSame($expected, DownloadWpCore::normalizeWpVersion($input));
    }

    /**
     * @return list<array{string, string}>
     */
    public static function provideWpVersions(): array
    {
        return [
            0 => ['foo', ''],
            1 => ['1foo', '1.0'],
            2 => ['12foo', '12.0'],
            3 => [' 1f..2o..0o ', '1.2'],
            4 => [' 1f..2o..2o ', '1.2.2'],
            5 => [' 1f..22o..2o ', ''],
            6 => ['1', '1.0'],
            7 => ['.1 ', '1.0'],
            8 => ['1foo-beta', '1.0'],
            9 => ['1.1-beta', '1.1'],
            10 => ['1.11-beta', ''],
            11 => [' 1.9-beta', '1.9'],
            12 => ['1.9.99-beta', '1.9.99'],
            13 => ['1.9.0-beta', '1.9'],
            14 => ['1.9.0-beta ', '1.9'],
            15 => ['  1.9.0.3.5', '1.9'],
            16 => ['1.9.9..3.5', '1.9.9'],
            17 => ['1.9.9.3-beta1.5', '1.9.9'],
            18 => ['1..9..5', '1.9.5'],
            19 => ['1..9..9', '1.9.9'],
            20 => ['0.9.3', '0.9.3'],
            21 => ['0.9.3.', '0.9.3'],
            22 => ['0.9', '0.9'],
            23 => ['0.0-beta1', ''],
            24 => ['0', ''],
            25 => ['0-beta', ''],
            26 => ['0.1-beta1', '0.1'],
            27 => ['1.9.0.3-beta1.5', '1.9'],
        ];
    }
}
