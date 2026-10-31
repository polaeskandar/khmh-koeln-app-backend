<?php

declare(strict_types=1);

namespace App\Context\People\Infrastructure\Persistence\Repository;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrinePersonRepository implements PersonRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(Person $person, Membership ...$memberships): void
    {
        $this->entityManager->persist($person);

        foreach ($memberships as $membership) {
            $this->entityManager->persist($membership);
        }

        $this->entityManager->flush();
    }
}
