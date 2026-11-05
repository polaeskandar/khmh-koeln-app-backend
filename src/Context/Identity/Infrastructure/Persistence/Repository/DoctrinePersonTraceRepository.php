<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Repository\PersonTraceRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePersonTraceRepository implements PersonTraceRepositoryInterface
{
    private const string UNLINK = <<<'SQL'
        UPDATE identity.app_user SET person_id = NULL, age_check = NULL, guardian_consent_note = NULL
        WHERE person_id = :person
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function forget(Uuid $personId): int
    {
        return (int) $this->entityManager->getConnection()->executeStatement(self::UNLINK, [
            'person' => $personId->toRfc4122(),
        ]);
    }
}
