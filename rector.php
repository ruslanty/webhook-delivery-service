<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths(
        [
            __DIR__ . '/src',
            __DIR__ . '/tests',
        ]
    )
    ->withPhpVersion(PhpVersion::PHP_86)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true
    );
