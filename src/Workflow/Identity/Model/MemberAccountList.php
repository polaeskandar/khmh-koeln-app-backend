<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Application\Service\MemberAccounts;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PersonDirectory;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Client\Calendar;
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

    public static function lookedUpIn(
        MemberAccounts $accounts,
        PersonDirectory $directory,
        StructureLookup $structure,
        Calendar $calendar,
    ): self {
        $rows = new ArrayCollection($accounts->listed());
        $personIds = $rows->map(static fn (array $row): Uuid => Uuid::fromString($row['personId']));
        $records = LinkedRecords::lookedUpIn($directory, $structure, $personIds->getValues());
        $collator = new Collator(self::COLLATION);

        $items = Collections::sortedBy(
            $rows->map(static fn (array $row): MemberAccountItem => self::itemOf($row, $records, $calendar)),
            keyOf: static fn (MemberAccountItem $item): string => (string) $collator->getSortKey($item->account->name),
        );

        return new self($calendar->today()->toString(), $items);
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
    private static function itemOf(array $row, LinkedRecords $records, Calendar $calendar): MemberAccountItem
    {
        return new MemberAccountItem(
            new MemberAccountView($row['id'], $row['name'], $row['email'], $row['isParent'], $row['disabled']),
            $records->viewOf(Uuid::fromString($row['personId'])),
            new MemberAgeView($row['ageCheck'], $row['guardianConsentNote']),
            ApprovalView::of($row['approvedAt'], $row['approverId'], $row['approverName'], $calendar),
        );
    }
}
