<?php

declare(strict_types=1);

use NunoMaduro\PhpInsights\Domain\Insights\CyclomaticComplexityIsHigh;
use NunoMaduro\PhpInsights\Domain\Sniffs\ForbiddenSetterSniff;
use PHP_CodeSniffer\Standards\Generic\Sniffs\Files\LineLengthSniff;
use PHP_CodeSniffer\Standards\Generic\Sniffs\Formatting\SpaceAfterNotSniff;
use SlevomatCodingStandard\Sniffs\Classes\ForbiddenPublicPropertySniff;
use SlevomatCodingStandard\Sniffs\Classes\SuperfluousInterfaceNamingSniff;
use SlevomatCodingStandard\Sniffs\Functions\UnusedParameterSniff;

return [
    'preset' => 'symfony',
    'exclude' => ['config/reference.php', 'Infrastructure/Persistence/Migrations', 'public/bundles', 'var'],
    'remove' => [SpaceAfterNotSniff::class, SuperfluousInterfaceNamingSniff::class],
    'config' => [
        LineLengthSniff::class => ['lineLimit' => 120, 'absoluteLineLimit' => 120],
        ForbiddenSetterSniff::class => ['exclude' => ['src/Platform/OpenApi/Service']],
        UnusedParameterSniff::class => ['exclude' => ['src/Platform/OpenApi/Service/ArrayShapes.php']],
        ForbiddenPublicPropertySniff::class => ['exclude' => ['src/Interface/Console']],
        CyclomaticComplexityIsHigh::class => ['maxComplexity' => 10],
    ],
    'requirements' => [
        'min-quality' => 100,
        'min-complexity' => 100,
        'min-architecture' => 100,
        'min-style' => 100,
    ],
];
