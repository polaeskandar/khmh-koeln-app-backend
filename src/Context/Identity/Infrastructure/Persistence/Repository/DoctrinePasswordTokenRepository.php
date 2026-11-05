<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Entity\PasswordToken;
use App\Context\Identity\Domain\Repository\PasswordTokenRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrinePasswordTokenRepository implements PasswordTokenRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function replaceUnused(#[\SensitiveParameter] PasswordToken $token): void
    {
        $this->entityManager->wrapInTransaction(function () use ($token): void {
            $this->entityManager->getConnection()->delete('identity.password_token', [
                'user_id' => $token->user()->id()->toRfc4122(),
                'used_at' => null,
            ]);

            $this->entityManager->persist($token);
            $this->entityManager->flush();
        });
    }
}
