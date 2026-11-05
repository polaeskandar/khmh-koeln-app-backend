<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Repository\UnconfirmedAccountRepositoryInterface;
use App\Library\Types\Model\Type;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineUnconfirmedAccountRepository implements UnconfirmedAccountRepositoryInterface
{
    private const string PURGE = <<<'SQL'
        DELETE FROM identity.app_user u
        WHERE u.approved_at IS NULL AND u.email_confirmed_at IS NULL AND u.created_at < :before
          AND NOT EXISTS (
            SELECT 1 FROM identity.email_confirmation_token t
            WHERE t.user_id = u.id AND t.used_at IS NULL AND t.expires_at > :now
          )
        RETURNING u.id::text
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<Uuid>
     */
    #[Override]
    public function purgeExpired(DateTimeImmutable $registeredBefore, DateTimeImmutable $now): array
    {
        $ids = $this->entityManager
            ->getConnection()
            ->executeQuery(
                self::PURGE,
                ['before' => $registeredBefore, 'now' => $now],
                ['before' => Types::DATETIMETZ_IMMUTABLE, 'now' => Types::DATETIMETZ_IMMUTABLE],
            )
            ->fetchFirstColumn();

        return new ArrayCollection($ids)->map(static fn (mixed $id): Uuid => Uuid::fromString(
            Type::of($id)->toString(),
        ))->getValues();
    }
}
