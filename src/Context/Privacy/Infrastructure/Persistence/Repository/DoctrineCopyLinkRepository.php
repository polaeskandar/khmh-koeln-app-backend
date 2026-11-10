<?php

declare(strict_types=1);

namespace App\Context\Privacy\Infrastructure\Persistence\Repository;

use App\Context\Privacy\Domain\Entity\CopyLink;
use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Context\Privacy\Domain\Enum\RequestStatus;
use App\Context\Privacy\Domain\Repository\CopyLinkRepositoryInterface;
use App\Library\Types\Model\Type;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Override;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineCopyLinkRepository implements CopyLinkRepositoryInterface
{
    private const string SPEND = <<<'SQL'
        UPDATE privacy.copy_link l SET used_at = :at
        FROM privacy.data_request r
        WHERE r.id = l.data_request_id
          AND l.token_hash = :hash AND l.used_at IS NULL AND l.expires_at > :at
          AND r.status = :open AND (r.person_id IS NOT NULL OR r.app_user_id IS NOT NULL)
        RETURNING r.id::text
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function replaceUnused(CopyLink $issued): void
    {
        $this->entityManager->wrapInTransaction(function () use ($issued): void {
            $this->entityManager->getConnection()->delete('privacy.copy_link', [
                'data_request_id' => $issued->request()->id()->toRfc4122(),
                'used_at' => null,
            ]);

            $this->entityManager->persist($issued);
            $this->entityManager->flush();
        });
    }

    #[Override]
    public function spend(#[SensitiveParameter] string $tokenHash, DateTimeImmutable $at, callable $use): mixed
    {
        return $this->entityManager->wrapInTransaction(function () use ($tokenHash, $at, $use): mixed {
            $requestId = $this->spentFor($tokenHash, $at);

            if ($requestId === null) {
                return null;
            }

            $used = $use(
                $this->entityManager->find(DataRequest::class, $requestId) ?? throw new LogicException(
                    'A copy link belongs to a request.',
                ),
            );

            $this->entityManager->flush();

            return $used;
        });
    }

    #[Override]
    public function latestOf(Uuid $requestId): ?CopyLink
    {
        return $this->entityManager->getRepository(CopyLink::class)->findOneBy(['request' => $requestId], [
            'createdAt' => 'DESC',
            'id' => 'DESC',
        ]);
    }

    private function spentFor(#[SensitiveParameter] string $tokenHash, DateTimeImmutable $at): ?Uuid
    {
        $requestId = Type::of(
            $this->entityManager
                ->getConnection()
                ->executeQuery(
                    self::SPEND,
                    ['hash' => $tokenHash, 'at' => $at, 'open' => RequestStatus::Open->value],
                    ['at' => Types::DATETIMETZ_IMMUTABLE],
                )
                ->fetchOne(),
        )->toStringOrNull();

        return $requestId === null ? null : Uuid::fromString($requestId);
    }
}
