<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Entity\Account;
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
    private const string LISTED = <<<'SQL'
        SELECT u.id::text AS id, u.display_name AS name, u.email, u.disabled_at IS NOT NULL AS disabled,
          EXISTS (
            SELECT 1 FROM identity.user_role ur
            JOIN identity.role_permission rp ON rp.role_id = ur.role_id
            JOIN identity.permission p ON p.id = rp.permission_id
            WHERE ur.user_id = u.id AND p.resource = 'parentPortal'
          ) AS parent,
          u.person_id::text AS person, u.age_check, u.guardian_consent_note AS note, u.approved_at::text AS approved_at,
          a.id::text AS approver_id, a.display_name AS approver_name
        FROM identity.app_user u
        LEFT JOIN identity.app_user a ON a.id = u.approved_by
        WHERE u.person_id IS NOT NULL
          AND NOT EXISTS (
            SELECT 1 FROM identity.user_role ur
            JOIN identity.role_permission rp ON rp.role_id = ur.role_id
            JOIN identity.permission p ON p.id = rp.permission_id
            WHERE ur.user_id = u.id AND p.resource <> 'parentPortal'
          )
        ORDER BY lower(u.display_name), u.id
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function holderOf(Uuid $personId): ?Account
    {
        return $this->entityManager->getRepository(Account::class)->findOneBy(['member.personId' => $personId]);
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

    /**
     * @return list<array{
     *     id: string,
     *     name: string,
     *     email: string,
     *     disabled: bool,
     *     isParent: bool,
     *     personId: string,
     *     ageCheck: string|null,
     *     guardianConsentNote: string|null,
     *     approvedAt: string|null,
     *     approverId: string|null,
     *     approverName: string|null,
     * }>
     */
    #[Override]
    public function listed(): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(self::LISTED);

        return new ArrayCollection($rows)->map(static fn (array $row): array => [
            'id' => Type::of($row['id'] ?? null)->toString(),
            'name' => Type::of($row['name'] ?? null)->toString(),
            'email' => Type::of($row['email'] ?? null)->toString(),
            'disabled' => ($row['disabled'] ?? false) === true,
            'isParent' => ($row['parent'] ?? false) === true,
            'personId' => Type::of($row['person'] ?? null)->toString(),
            'ageCheck' => Type::of($row['age_check'] ?? null)->toStringOrNull(),
            'guardianConsentNote' => Type::of($row['note'] ?? null)->toStringOrNull(),
            'approvedAt' => Type::of($row['approved_at'] ?? null)->toStringOrNull(),
            'approverId' => Type::of($row['approver_id'] ?? null)->toStringOrNull(),
            'approverName' => Type::of($row['approver_name'] ?? null)->toStringOrNull(),
        ])->getValues();
    }
}
