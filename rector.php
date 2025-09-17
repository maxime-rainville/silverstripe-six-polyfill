<?php

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Expression\RemoveDeadStmtRector;
use Rector\DeadCode\Rector\StaticCall\RemoveParentCallWithoutParentRector;
use SilverStripePolyfill\Rector\Rules\RemoveClassRenameDeprecationRule;
use SilverStripePolyfill\Rector\Rules\ChangeClassNamespaceRule;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withPhpSets()
    ->withRules([
        RemoveClassRenameDeprecationRule::class,
        ChangeClassNamespaceRule::class,
        RemoveDeadStmtRector::class, // Clean up empty statements after removing deprecations
        RemoveParentCallWithoutParentRector::class, // Clean up unnecessary parent calls
    ])
    ->withAutoloadPaths([
        __DIR__ . '/vendor/autoload.php',
    ]);