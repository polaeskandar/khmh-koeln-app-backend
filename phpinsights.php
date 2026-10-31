<?php

declare(strict_types=1);

use PHP_CodeSniffer\Standards\Generic\Sniffs\Files\LineLengthSniff;
use PHP_CodeSniffer\Standards\Generic\Sniffs\Formatting\SpaceAfterNotSniff;
use SlevomatCodingStandard\Sniffs\Classes\SuperfluousInterfaceNamingSniff;

return [
    'preset' => 'symfony',
    'exclude' => ['config/reference.php', 'Infrastructure/Persistence/Migrations', 'public/bundles', 'var'],
    'remove' => [SpaceAfterNotSniff::class, SuperfluousInterfaceNamingSniff::class],
    'config' => [
        LineLengthSniff::class => ['lineLimit' => 120, 'absoluteLineLimit' => 120],
    ],
    'requirements' => [
        'min-quality' => 100,
        'min-complexity' => 100,
        'min-architecture' => 100,
        'min-style' => 100,
    ],
];
