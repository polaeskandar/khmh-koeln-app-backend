<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(Uuid $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }

    #[Override]
    public function findByEmail(string $email): ?User
    {
        return $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['profile.email.value' => Email::normalized($email)]);
    }
}
