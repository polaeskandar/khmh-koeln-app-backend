<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\Email;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
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
    public function findByEmail(string $email): ?User
    {
        return $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['profile.email.value' => Email::normalized($email)]);
    }

    #[Override]
    public function hasOtherActiveAccountThatCan(User $except, Action $action, Resource $resource): bool
    {
        return $this->entityManager
            ->getConnection()
            ->createQueryBuilder()
            ->select('1')
            ->from('identity.app_user', 'u')
            ->innerJoin('u', 'identity.user_role', 'ur', 'ur.user_id = u.id')
            ->innerJoin('ur', 'identity.role_permission', 'rp', 'rp.role_id = ur.role_id')
            ->innerJoin('rp', 'identity.permission', 'p', 'p.id = rp.permission_id')
            ->where('u.id <> :except')
            ->andWhere('u.approved_at IS NOT NULL')
            ->andWhere('u.disabled_at IS NULL')
            ->andWhere('p.action = :action')
            ->andWhere('p.resource = :resource')
            ->setParameter('except', $except->id()->toRfc4122())
            ->setParameter('action', $action->value)
            ->setParameter('resource', $resource->value)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne() !== false;
    }
}
