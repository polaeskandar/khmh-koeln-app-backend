<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Entity\ApiToken;
use App\Context\Identity\Domain\Repository\ApiTokenRepositoryInterface;
use App\Library\Types\Model\Type;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineApiTokenRepository implements ApiTokenRepositoryInterface
{
    private const string USE = <<<'SQL'
        UPDATE identity.api_token SET last_used_at = :now
        WHERE token_hash = :hash AND revoked_at IS NULL AND expires_at > :now
        RETURNING user_id::text
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(ApiToken $issued): void
    {
        $this->entityManager->persist($issued);
        $this->entityManager->flush();
    }

    #[Override]
    public function holderOf(#[SensitiveParameter] string $tokenHash, DateTimeImmutable $now): ?Uuid
    {
        $userId = Type::of(
            $this->entityManager
                ->getConnection()
                ->executeQuery(self::USE, ['hash' => $tokenHash, 'now' => $now], ['now' => Types::DATETIMETZ_IMMUTABLE])
                ->fetchOne(),
        )->toStringOrNull();

        return $userId === null ? null : Uuid::fromString($userId);
    }

    #[Override]
    public function revoke(#[SensitiveParameter] string $tokenHash, DateTimeImmutable $at): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE identity.api_token SET revoked_at = :at WHERE token_hash = :hash AND revoked_at IS NULL',
            ['at' => $at, 'hash' => $tokenHash],
            ['at' => Types::DATETIMETZ_IMMUTABLE],
        );
    }

    #[Override]
    public function revokeAllOf(Uuid $userId, DateTimeImmutable $at): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE identity.api_token SET revoked_at = :at WHERE user_id = :user AND revoked_at IS NULL',
            ['at' => $at, 'user' => $userId->toRfc4122()],
            ['at' => Types::DATETIMETZ_IMMUTABLE],
        );
    }
}
