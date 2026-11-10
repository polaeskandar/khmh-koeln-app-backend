<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use Symfony\Component\Uid\Uuid;

final readonly class Children
{
    public function __construct(
        private PersonRepositoryInterface $people,
    ) {
    }

    public function find(Uuid $personId): ?Person
    {
        return $this->people->find($personId);
    }

    public function active(Uuid $personId): Person
    {
        $child = $this->find($personId) ?? throw NotFound::of('person');

        return $child->status() === PersonStatus::Active ? $child : throw new RuleViolation('guardians.childInactive');
    }
}
