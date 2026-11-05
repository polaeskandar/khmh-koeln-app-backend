<?php

declare(strict_types=1);

namespace App\Workflow\Demo\Model;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\People\Domain\Entity\Person;
use Doctrine\Common\Collections\ArrayCollection;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class DemoCast
{
    /**
     * @param array<string, list<Person>> $people
     * @param array<string, list<Schedule>> $schedules
     */
    public function __construct(
        private array $people,
        private array $schedules,
    ) {
    }

    /**
     * @return list<Person>
     */
    public function membersOf(string $group): array
    {
        return $this->people[$group] ?? throw new LogicException('The demo has no ' . $group . '.');
    }

    public function namedOf(string $group): Person
    {
        return $this->membersOf($group)[0] ?? throw new LogicException('The demo group ' . $group . ' is empty.');
    }

    /**
     * @return list<Uuid>
     */
    public function firstIdsOf(string $group, int $count): array
    {
        $first = new ArrayCollection(new ArrayCollection($this->membersOf($group))->slice(offset: 0, length: $count));

        return $first->map(static fn (Person $person): Uuid => $person->id())->getValues();
    }

    /**
     * @return list<Schedule>
     */
    public function schedulesOf(string $group): array
    {
        return $this->schedules[$group] ?? throw new LogicException('The demo has no schedules for ' . $group . '.');
    }

    public function sundayClassOf(string $group): Schedule
    {
        return $this->schedulesOf($group)[0] ?? throw new LogicException('The demo group ' . $group . ' has no class.');
    }
}
