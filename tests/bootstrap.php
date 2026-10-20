<?php

declare(strict_types=1);

$vendor = getenv('BITRIX24_TEST_VENDOR') ?: dirname(__DIR__).'/vendor/autoload.php';

if (is_dir($vendor)) {
    $vendor = rtrim($vendor, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'autoload.php';
}

if (! is_file($vendor)) {
    fwrite(STDERR, "Composer autoload not found at [{$vendor}]. Run composer install or set BITRIX24_TEST_VENDOR.\n");
    exit(1);
}

/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require $vendor;
$loader->addPsr4('Nbcom\\Bitrix24\\', dirname(__DIR__).'/src');
$loader->addPsr4('Nbcom\\Bitrix24\\Tests\\', __DIR__);
