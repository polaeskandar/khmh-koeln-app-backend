<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Strings\Model\Text;
use Symfony\Component\Uid\Uuid;

final readonly class Attendees
{
    public function __construct(
        private PersonRepositoryInterface $people,
    ) {
    }

    public function find(string $personId): Person
    {
        return $this->people->find(Uuid::fromString($personId)) ?? throw NotFound::of('person');
    }

    public function markable(string $personId): Person
    {
        $person = $this->find($personId);

        if ($person->status() !== PersonStatus::Inactive) {
            return $person;
        }

        $name = $person->name();

        throw new RuleViolation('attendance.personInactive', [
            'name' => Text::format('%s %s', $name->firstName, $name->lastName)->toString(),
        ]);
    }
}
