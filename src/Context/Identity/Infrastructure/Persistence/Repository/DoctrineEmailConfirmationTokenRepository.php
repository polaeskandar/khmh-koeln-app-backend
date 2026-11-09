<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Entity\EmailConfirmationToken;
use App\Context\Identity\Domain\Repository\EmailConfirmationTokenRepositoryInterface;
use App\Library\Types\Model\Type;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Override;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineEmailConfirmationTokenRepository implements EmailConfirmationTokenRepositoryInterface
{
    private const string SPEND = <<<'SQL'
        UPDATE identity.email_confirmation_token SET used_at = :at
        WHERE token_hash = :hash AND used_at IS NULL AND expires_at > :at
        RETURNING user_id::text
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function replaceUnused(EmailConfirmationToken $issued): void
    {
        $this->entityManager->wrapInTransaction(function () use ($issued): void {
            $this->entityManager->getConnection()->delete('identity.email_confirmation_token', [
                'user_id' => $issued->account()->id()->toRfc4122(),
                'used_at' => null,
            ]);

            $this->entityManager->persist($issued);
            $this->entityManager->flush();
        });
    }

    #[Override]
    public function spend(#[SensitiveParameter] string $tokenHash, DateTimeImmutable $at, callable $use): bool
    {
        return $this->entityManager->wrapInTransaction(function () use ($tokenHash, $at, $use): bool {
            $accountId = $this->spentBy($tokenHash, $at);

            if ($accountId === null) {
                return false;
            }

            $use(
                $this->entityManager->find(Account::class, $accountId) ?? throw new LogicException(
                    'The token has no user.',
                ),
            );
            $this->entityManager->flush();

            return true;
        });
    }

    private function spentBy(#[SensitiveParameter] string $tokenHash, DateTimeImmutable $at): ?Uuid
    {
        $accountId = Type::of(
            $this->entityManager
                ->getConnection()
                ->executeQuery(self::SPEND, ['hash' => $tokenHash, 'at' => $at], ['at' => Types::DATETIMETZ_IMMUTABLE])
                ->fetchOne(),
        )->toStringOrNull();

        return $accountId === null ? null : Uuid::fromString($accountId);
    }
}
