<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Repository\PersonTraceRepositoryInterface;
use App\Library\Types\Model\Integers;
use App\Library\Types\Model\Type;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePersonTraceRepository implements PersonTraceRepositoryInterface
{
    private const string FORGET_CONSENT = <<<'SQL'
        UPDATE identity.app_user SET age_check = NULL, guardian_consent_note = NULL
        WHERE person_id = :person AND age_check IS NOT NULL
        SQL;

    private const string LINKED_ACCOUNT = 'SELECT id::text FROM identity.app_user WHERE person_id = :person';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function forget(Uuid $personId): int
    {
        return Integers::of($this->entityManager->getConnection()->executeStatement(self::FORGET_CONSENT, [
            'person' => $personId->toRfc4122(),
        ]));
    }

    #[Override]
    public function accountOf(Uuid $personId): ?Uuid
    {
        $id = Type::of($this->entityManager->getConnection()->fetchOne(self::LINKED_ACCOUNT, [
            'person' => $personId->toRfc4122(),
        ]))->toStringOrNull();

        return $id === null ? null : Uuid::fromString($id);
    }
}
