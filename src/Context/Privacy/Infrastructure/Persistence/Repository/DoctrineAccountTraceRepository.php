<?php

declare(strict_types=1);

namespace App\Context\Privacy\Infrastructure\Persistence\Repository;

use App\Context\Privacy\Domain\Repository\AccountTraceRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAccountTraceRepository implements AccountTraceRepositoryInterface
{
    public const array ACTOR_COLUMNS = [
        ['privacy.data_request', 'closed_by'],
    ];

    public const array SUBJECT_COLUMNS = [
        ['privacy.data_request', 'app_user_id'],
    ];

    private const string FORGET_SUBJECT = <<<'SQL'
        UPDATE privacy.data_request
        SET app_user_id = NULL, erased_subject_kind = COALESCE(erased_subject_kind, 'account')
        WHERE app_user_id = ?
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function forget(Uuid $accountId): int
    {
        $connection = $this->entityManager->getConnection();
        $id = $accountId->toRfc4122();
        $emailed = (int) $connection->update('privacy.copy_link', ['recipient_id' => null], ['recipient_id' => $id]);
        $closed = (int) $connection->update('privacy.data_request', ['closed_by' => null], ['closed_by' => $id]);

        return $emailed + $closed + (int) $connection->executeStatement(self::FORGET_SUBJECT, [$id]);
    }
}
