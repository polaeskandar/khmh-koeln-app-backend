<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\People\Domain\Entity;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\ValueObject\PersonName;
use App\Tests\Support\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class Rosters
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function person(string $firstName, string $lastName = 'Iskander'): Person
    {
        $person = new Person(PersonName::of($firstName, $lastName, null));
        new Database($this->container)->persisted($person);

        return $person;
    }

    public function place(
        Person $person,
        Service $service,
        ?Stage $stage,
        string $startsOn,
        ?string $endsOn = null,
    ): void {
        new Database($this->container)
            ->connection()
            ->insert('people.membership', [
                'id' => Uuid::v7()->toRfc4122(),
                'person_id' => $person->id()->toRfc4122(),
                'service_id' => $service->id()->toRfc4122(),
                'stage_id' => $stage?->id()->toRfc4122(),
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
            ]);
    }
}
