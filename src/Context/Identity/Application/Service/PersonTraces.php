<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Repository\PersonTraceRepositoryInterface;
use App\Platform\Privacy\Contract\PersonReferences;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class PersonTraces implements PersonReferences
{
    public function __construct(
        private PersonTraceRepositoryInterface $traces,
    ) {
    }

    #[Override]
    public function forgetPerson(Uuid $personId): int
    {
        return $this->traces->forget($personId);
    }
}
