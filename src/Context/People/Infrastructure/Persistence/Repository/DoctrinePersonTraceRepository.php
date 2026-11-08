<?php

declare(strict_types=1);

namespace App\Context\People\Infrastructure\Persistence\Repository;

use App\Context\People\Domain\Repository\PersonTraceRepositoryInterface;
use App\Context\People\Domain\ValueObject\ForgottenPerson;
use App\Library\Types\Model\Integers;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePersonTraceRepository implements PersonTraceRepositoryInterface
{
    private const string GUARDIAN_LINKS = 'SELECT count(*) FROM people.guardian_relationship WHERE user_id = :account';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function forget(Uuid $personId): ForgottenPerson
    {
        $connection = $this->entityManager->getConnection();
        $id = $personId->toRfc4122();

        $forgotten = new ForgottenPerson(
            memberships: (int) $connection->delete('people.membership', ['person_id' => $id]),
            guardianLinks: (int) $connection->delete('people.guardian_relationship', ['person_id' => $id]),
        );

        $connection->delete('people.person', ['id' => $id]);
        $this->entityManager->clear();

        return $forgotten;
    }

    #[Override]
    public function guardianLinksOf(Uuid $accountId): int
    {
        return Integers::of($this->entityManager->getConnection()->fetchOne(self::GUARDIAN_LINKS, [
            'account' => $accountId->toRfc4122(),
        ]));
    }
}
