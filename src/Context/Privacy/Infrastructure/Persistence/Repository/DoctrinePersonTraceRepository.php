<?php

declare(strict_types=1);

namespace App\Context\Privacy\Infrastructure\Persistence\Repository;

use App\Context\Privacy\Domain\Repository\PersonTraceRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePersonTraceRepository implements PersonTraceRepositoryInterface
{
    private const string FORGET_SUBJECT = <<<'SQL'
        UPDATE privacy.data_request
        SET person_id = NULL, erased_subject_kind = COALESCE(erased_subject_kind, 'member')
        WHERE person_id = ?
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function forget(Uuid $personId): int
    {
        return (int) $this->entityManager->getConnection()->executeStatement(self::FORGET_SUBJECT, [
            $personId->toRfc4122(),
        ]);
    }
}
