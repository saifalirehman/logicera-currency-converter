<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/Api',
        __DIR__ . '/Controller',
        __DIR__ . '/Model',
        __DIR__ . '/ViewModel',
    ]);

    // Enforce modern PHP idioms up to the module's minimum version (PHP 8.1):
    // constructor property promotion, readonly properties, etc.
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_81,
    ]);
};
