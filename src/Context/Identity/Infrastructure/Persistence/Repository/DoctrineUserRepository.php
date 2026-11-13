<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\Username;
use App\Library\Problem\Exception\NotFound;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\LockMode;
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
    public function remove(User $user): void
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    #[Override]
    public function withWriteLock(Uuid $id, callable $work): void
    {
        $this->entityManager->wrapInTransaction(function () use ($id, $work): void {
            $loaded = $this->entityManager->getUnitOfWork()->tryGetById(['id' => $id], User::class);

            if ($loaded !== false) {
                $this->entityManager->detach($loaded);
            }

            $work(
                $this->entityManager->find(User::class, $id, LockMode::PESSIMISTIC_WRITE) ?? throw NotFound::of('user'),
            );
        });
    }

    /**
     * @param list<Uuid> $ids
     */
    #[Override]
    public function findByIds(array $ids): ReadableCollection
    {
        return new ArrayCollection(
            $ids === [] ? [] : $this->entityManager->getRepository(User::class)->findBy(['id' => $ids]),
        );
    }

    #[Override]
    public function findByUsername(string $username): ?User
    {
        return $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['username.value' => Username::normalized($username)]);
    }
}
