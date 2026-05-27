<?php

declare(strict_types=1);

// Load zRoute classes directly, as they may not be present in the PSR-4 map
// when the package was installed from source (e.g. via composer install from
// a shallow environment).  A normal `composer install` would include them.
$zrouteSrc = __DIR__ . '/../vendor/tandrezone/zroute/src';
if (!class_exists('zRoute\Route') && is_dir($zrouteSrc)) {
    require_once $zrouteSrc . '/Route.php';
    require_once $zrouteSrc . '/Router.php';
}

require_once __DIR__ . '/../vendor/autoload.php';
