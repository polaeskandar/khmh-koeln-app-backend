<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Entity\PasswordToken;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\PasswordTokenRepositoryInterface;
use App\Library\Types\Model\Integers;
use App\Library\Types\Model\Type;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePasswordTokenRepository implements PasswordTokenRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function countFor(Uuid $userId): int
    {
        return Integers::of(
            $this->entityManager
                ->getConnection()
                ->createQueryBuilder()
                ->select('count(*)')
                ->from('identity.password_token')
                ->where('user_id = :user')
                ->setParameter('user', $userId->toRfc4122())
                ->fetchOne(),
        );
    }

    #[Override]
    public function replaceUnused(#[\SensitiveParameter] PasswordToken $token): void
    {
        $this->entityManager->wrapInTransaction(function () use ($token): void {
            $this->forgetUnusedOf($token->user()->id());
            $this->entityManager->persist($token);
            $this->entityManager->flush();
        });
    }

    #[Override]
    public function forgetUnusedOf(Uuid $userId): void
    {
        $this->entityManager->getConnection()->delete('identity.password_token', [
            'user_id' => $userId->toRfc4122(),
            'used_at' => null,
        ]);
    }

    #[Override]
    public function spend(#[\SensitiveParameter] string $tokenHash, DateTimeImmutable $at, callable $use): bool
    {
        return $this->entityManager->wrapInTransaction(function () use ($tokenHash, $at, $use): bool {
            $userId = $this->spentBy($tokenHash, $at);

            if ($userId === null) {
                return false;
            }

            $this->forgetUnusedOf($userId);
            $use(
                $this->entityManager->find(User::class, $userId) ?? throw new LogicException('The token has no user.'),
            );
            $this->entityManager->flush();

            return true;
        });
    }

    private function spentBy(#[\SensitiveParameter] string $tokenHash, DateTimeImmutable $at): ?Uuid
    {
        $userId = Type::of(
            $this->entityManager
                ->getConnection()
                ->executeQuery(<<<'SQL'
                    UPDATE identity.password_token t SET used_at = :at
                    FROM identity.app_user u
                    WHERE t.token_hash = :hash AND t.used_at IS NULL AND t.expires_at > :at
                      AND u.id = t.user_id AND u.disabled_at IS NULL
                    RETURNING t.user_id
                    SQL, ['hash' => $tokenHash, 'at' => $at], ['at' => Types::DATETIMETZ_IMMUTABLE])
                ->fetchOne(),
        )->toStringOrNull();

        return $userId === null ? null : Uuid::fromString($userId);
    }
}
