<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\Email;
use App\Library\Problem\Exception\NotFound;
use App\Library\Types\Model\ListOf;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAccountRepository implements AccountRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(Account $account): void
    {
        $this->entityManager->persist($account);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(Uuid $id): ?Account
    {
        return $this->entityManager->find(Account::class, $id);
    }

    #[Override]
    public function remove(Account $account): void
    {
        $this->entityManager->remove($account);
        $this->entityManager->flush();
    }

    #[Override]
    public function pending(int $limit): ReadableCollection
    {
        $confirmed = Criteria::create()
            ->where(Criteria::expr()->isNull('status.approvedAt'))
            ->andWhere(Criteria::expr()->neq('status.emailConfirmedAt', null))
            ->orderBy(['createdAt' => Order::Ascending, 'id' => Order::Ascending])
            ->setMaxResults($limit);

        return new ArrayCollection(
            $this->entityManager->getRepository(Account::class)->matching($confirmed)->getValues(),
        );
    }

    #[Override]
    public function withWriteLock(Uuid $id, callable $work): void
    {
        $this->entityManager->wrapInTransaction(function () use ($id, $work): void {
            $loaded = $this->entityManager->getUnitOfWork()->tryGetById(['id' => $id], Account::class);

            if ($loaded !== false) {
                $this->entityManager->detach($loaded);
            }

            $work(
                $this->entityManager->find(Account::class, $id, LockMode::PESSIMISTIC_WRITE) ?? throw NotFound::of(
                    'user',
                ),
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
            $ids === [] ? [] : $this->entityManager->getRepository(Account::class)->findBy(['id' => $ids]),
        );
    }

    #[Override]
    public function activeThatCan(Action $action, Resource $resource): ReadableCollection
    {
        $query = $this->entityManager->createQuery(<<<'DQL'
            SELECT DISTINCT u FROM App\Context\Identity\Domain\Entity\Account u
            JOIN u.roles r
            JOIN r.permissions p
            WHERE u.status.approvedAt IS NOT NULL
              AND u.status.disabledAt IS NULL
              AND p.action = :action
              AND p.resource = :resource
            DQL)->setParameters(['action' => $action, 'resource' => $resource]);

        return new ArrayCollection(ListOf::instances($query->getResult(), Account::class));
    }

    #[Override]
    public function findByEmail(string $email): ?Account
    {
        return $this->entityManager
            ->getRepository(Account::class)
            ->findOneBy(['profile.email.value' => Email::normalized($email)]);
    }

    #[Override]
    public function hasOtherActiveAccountThatCan(Account $except, Action $action, Resource $resource): bool
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
