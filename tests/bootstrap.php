<?php

declare(strict_types=1);

// Support both standalone install (vendor/) and monorepo (../../vendor/).
$autoloadPaths = [
    \dirname(__DIR__).'/vendor/autoload.php',
    \dirname(__DIR__, 3).'/vendor/autoload.php',
];

$loader = null;
foreach ($autoloadPaths as $path) {
    if (\file_exists($path)) {
        $loader = require $path;
        break;
    }
}

if (null === $loader) {
    throw new RuntimeException('Could not find vendor/autoload.php. Run "composer install".');
}

$loader->addPsr4('Tests\\', [__DIR__]);
$loader->addPsr4('ChamberOrchestra\\TranslationBundle\\', [\dirname(__DIR__)]);
