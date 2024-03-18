<?php

declare(strict_types=1);

// phpcs:disable

$testsDir = str_replace('\\', '/', __DIR__);
$libDir = dirname($testsDir);
$vendorDir = "{$libDir}/vendor";
$autoload = "{$vendorDir}/autoload.php";

if (!is_file($autoload)) {
    die('Please install via Composer before running tests.');
}

putenv('VIP_COMPOSER_PLUGIN_TESTS_BASE_PATH=' . $testsDir);
putenv('VIP_COMPOSER_PLUGIN_LIBRARY_PATH=' . $libDir);
putenv('VIP_COMPOSER_PLUGIN_VENDOR_PATH=' . $vendorDir);

error_reporting(E_ALL ^ E_DEPRECATED);

if (!defined('PHPUNIT_COMPOSER_INSTALL')) {
    define('PHPUNIT_COMPOSER_INSTALL', $autoload);
    require_once $autoload;
}

unset($libDir, $testsDir, $vendorDir, $autoload);
