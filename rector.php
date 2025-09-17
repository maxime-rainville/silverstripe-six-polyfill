<?php

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Expression\RemoveDeadStmtRector;
use Rector\DeadCode\Rector\StaticCall\RemoveParentCallWithoutParentRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withPhpSets()
    ->withRules([
        RemoveDeadStmtRector::class, // Clean up empty statements
        RemoveParentCallWithoutParentRector::class, // Clean up unnecessary parent calls
    ]);

// Note: Custom namespace and deprecation transformations will be handled
// by the refresh script itself for better control