<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Entity\User;
use App\Context\People\Application\Service\PeopleExports;
use App\Context\People\Domain\Entity\Person;
use App\Context\Privacy\Application\Service\DataRequests;
use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Library\Collections\Model\Collections;
use App\Workflow\Identity\Model\AccountReference;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class DataRequestRows
{
    /**
     * @param ReadableCollection<string, Person> $people
     * @param ReadableCollection<string, User> $accounts
     */
    private function __construct(
        private ReadableCollection $people,
        private ReadableCollection $accounts,
        private DataRequests $requests,
    ) {
    }

    /**
     * @param list<DataRequest> $requests
     *
     * @return list<DataRequestRow>
     */
    public static function of(
        array $requests,
        DataRequests $service,
        PeopleExports $people,
        AccountLookup $accounts,
    ): array {
        $shown = new ArrayCollection($requests);
        $personIds = Collections::mapPresent($shown, static fn (DataRequest $r): ?Uuid => $r->subject()->personId);

        $accountIds = [
            ...Collections::mapPresent(
                $shown,
                static fn (DataRequest $r): ?Uuid => $r->subject()->accountId,
            )->getValues(),
            ...Collections::mapPresent($shown, static fn (DataRequest $r): ?Uuid => $r->closing()->by)->getValues(),
        ];

        $rows = new self(
            Collections::firstPerKey($people->people($personIds->getValues()), keyOf: self::personKey(...)),
            Collections::firstPerKey($accounts->accounts($accountIds), keyOf: self::accountKey(...)),
            $service,
        );

        return $shown->map($rows->rowOf(...))->getValues();
    }

    private function rowOf(DataRequest $request): DataRequestRow
    {
        $closer = $this->accounts->get($request->closing()->by?->toRfc4122() ?? '');

        return DataRequestRow::of(
            $request,
            RequestSubjectName::of($request->subject(), $this->people, $this->accounts),
            $closer === null ? null : AccountReference::of($closer),
            $this->requests,
        );
    }

    private static function personKey(Person $person): string
    {
        return $person->id()->toRfc4122();
    }

    private static function accountKey(User $user): string
    {
        return $user->id()->toRfc4122();
    }
}
