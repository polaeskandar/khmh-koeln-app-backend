<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Repository;

use App\Context\Organization\Domain\Entity\RegistrationService;
use App\Context\Organization\Domain\Repository\RegistrationServiceRepositoryInterface;
use App\Library\Types\Model\ListOf;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineRegistrationServiceRepository implements RegistrationServiceRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function add(RegistrationService $registrationService): void
    {
        $this->entityManager->persist($registrationService);
        $this->entityManager->flush();
    }

    /**
     * @param list<Uuid> $accountIds
     */
    #[Override]
    public function ofAccounts(array $accountIds): ReadableCollection
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('r', 's')
            ->from(RegistrationService::class, 'r')
            ->join('r.service', 's')
            ->where('r.accountId IN (:accounts)')
            ->orderBy('s.name.key', 'ASC')
            ->setParameter('accounts', $accountIds)
            ->getQuery();

        return new ArrayCollection(ListOf::instances($query->getResult(), RegistrationService::class));
    }

    #[Override]
    public function removeFor(Uuid $accountId): void
    {
        $this->entityManager->getConnection()->delete('organization.registration_service', [
            'account_id' => $accountId->toRfc4122(),
        ]);
    }
}
