<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\MemberAccountRepositoryInterface;
use App\Library\Collections\Model\Collections;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineMemberAccountRepository implements MemberAccountRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function holderOf(Uuid $personId): ?User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['member.personId' => $personId]);
    }

    /**
     * @param list<Uuid> $personIds
     *
     * @return list<string>
     */
    #[Override]
    public function linkedAmong(array $personIds): array
    {
        $linked = $this->entityManager
            ->getConnection()
            ->createQueryBuilder()
            ->select('person_id::text')
            ->from('identity.app_user')
            ->where('person_id IN (:people)')
            ->setParameter(
                'people',
                new ArrayCollection($personIds)->map(static fn (Uuid $id): string => $id->toRfc4122())->getValues(),
                ArrayParameterType::STRING,
            )
            ->fetchFirstColumn();

        return new ArrayCollection($linked)->map(
            static fn (mixed $id): string => Type::of($id)->toString(),
        )->getValues();
    }

    /**
     * @param list<Uuid> $personIds
     *
     * @return array<string, string>
     */
    #[Override]
    public function activeAccountsOf(array $personIds): array
    {
        $rows = $this->entityManager
            ->getConnection()
            ->createQueryBuilder()
            ->select('person_id::text AS person', 'id::text AS account')
            ->from('identity.app_user')
            ->where('person_id IN (:people)')
            ->andWhere('approved_at IS NOT NULL')
            ->andWhere('disabled_at IS NULL')
            ->setParameter(
                'people',
                new ArrayCollection($personIds)->map(static fn (Uuid $id): string => $id->toRfc4122())->getValues(),
                ArrayParameterType::STRING,
            )
            ->fetchAllAssociative();

        return Collections::firstPerKey(new ArrayCollection($rows), keyOf: static fn (array $row): string => Type::of(
            $row['person'] ?? null,
        )->toString())->map(static fn (array $row): string => Type::of($row['account'] ?? null)->toString())->toArray();
    }
}
