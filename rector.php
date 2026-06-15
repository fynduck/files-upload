<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/bootstrap',
        __DIR__ . '/config',
        __DIR__ . '/lang',
        __DIR__ . '/public',
        __DIR__ . '/resources',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/bootstrap/cache/*',
    ])
    ->withTypeCoverageLevel(5)
    ->withDeadCodeLevel(10)
    ->withCodeQualityLevel(10)
    ->withImportNames(removeUnusedImports: true)
    ->withTypeCoverageDocblockLevel(2)
    ->withCodingStyleLevel(3);
