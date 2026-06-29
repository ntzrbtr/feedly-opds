<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/database',
    ])
    // Define used sets.
    ->withSets([
        \Rector\Set\ValueObject\LevelSetList::UP_TO_PHP_84,
        \RectorLaravel\Set\LaravelSetList::LARAVEL_120,
    ])
    // Skip rules or files.
    ->withSkip([
        // We don't want to use arrow functions everywhere.
        \Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector::class,
    ])
    // Don't use imports for classes (for now).
    ->withImportNames(false)
    // Set stuff to apply.
    ->withPreparedSets(deadCode: true, codeQuality: true, codingStyle: true, typeDeclarations: true);
