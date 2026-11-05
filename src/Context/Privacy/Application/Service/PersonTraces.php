<?php

declare(strict_types=1);

namespace App\Context\Privacy\Application\Service;

use App\Context\Privacy\Domain\Repository\PersonTraceRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class PersonTraces
{
    public function __construct(
        private PersonTraceRepositoryInterface $traces,
    ) {
    }

    public function forget(Uuid $personId): int
    {
        return $this->traces->forget($personId);
    }
}
