<?php

declare(strict_types=1);

namespace App\Interface\Application\Health\Model;

final readonly class Readiness
{
    public const string OK = 'ok';

    public const string UNAVAILABLE = 'unavailable';

    /**
     * @param array<string, self::OK|self::UNAVAILABLE> $checks
     */
    public function __construct(
        public string $status,
        public array $checks,
    ) {
    }
}
