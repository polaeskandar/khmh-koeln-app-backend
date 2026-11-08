<?php

declare(strict_types=1);

namespace App\Context\Privacy\Application\Service;

use App\Context\Privacy\Domain\Repository\PersonTraceRepositoryInterface;
use App\Platform\Privacy\Contract\PersonReferences;
use App\Platform\Privacy\Model\ErasedPerson;
use Override;

final readonly class PersonTraces implements PersonReferences
{
    public function __construct(
        private PersonTraceRepositoryInterface $traces,
    ) {
    }

    /**
     * @return array<string, int>
     */
    #[Override]
    public function forgetPerson(ErasedPerson $person): array
    {
        $this->traces->forget($person->id, $person->group);

        return [];
    }
}
