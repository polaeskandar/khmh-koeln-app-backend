<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Library\Collections\Model\Collections;
use App\Library\Time\Model\LocalDate;
use Collator;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class MemberAccountList
{
    private const string COLLATION = 'de';

    /**
     * @param list<MemberAccountItem> $items
     */
    private function __construct(
        public string $today,
        public array $items,
    ) {
    }

    /**
     * @param list<array{
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
     * }> $rows
     */
    public static function of(array $rows, LinkedRecords $records, LocalDate $today): self
    {
        $collator = new Collator(self::COLLATION);

        $items = Collections::sortedBy(
            new ArrayCollection($rows)->map(static fn (array $row): MemberAccountItem => self::itemOf($row, $records)),
            keyOf: static fn (MemberAccountItem $item): string => (string) $collator->getSortKey($item->account->name),
        );

        return new self($today->toString(), $items);
    }

    /**
     * @param list<array{
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
     * }> $rows
     *
     * @return list<Uuid>
     */
    public static function personIdsOf(array $rows): array
    {
        $ids = [];

        foreach ($rows as $row) {
            $ids[] = Uuid::fromString($row['personId']);
        }

        return $ids;
    }

    /**
     * @param array{
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
     * } $row
     */
    private static function itemOf(array $row, LinkedRecords $records): MemberAccountItem
    {
        return new MemberAccountItem(
            new MemberAccountView($row['id'], $row['name'], $row['email'], $row['isParent'], $row['disabled']),
            $records->viewOf(Uuid::fromString($row['personId'])),
            MemberAgeView::of($row['ageCheck'], $row['guardianConsentNote']),
            ApprovalView::of($row['approvedAt'], $row['approverId'], $row['approverName']),
        );
    }
}
