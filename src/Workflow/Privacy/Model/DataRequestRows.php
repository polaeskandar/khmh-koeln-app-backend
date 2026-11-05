<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\People\Domain\Entity\Person;
use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Library\Collections\Model\Collections;
use App\Workflow\Identity\Model\AccountReferences;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class DataRequestRows
{
    /**
     * @param ReadableCollection<non-empty-string, Person> $people
     * @param ReadableCollection<non-empty-string, User> $accounts
     */
    private function __construct(
        private ReadableCollection $people,
        private ReadableCollection $accounts,
        private AccountReferences $references,
    ) {
    }

    /**
     * @param list<DataRequest> $requests
     * @param ReadableCollection<int, Person> $people
     * @param ReadableCollection<int, User> $accounts
     *
     * @return list<DataRequestRow>
     */
    public static function of(array $requests, ReadableCollection $people, ReadableCollection $accounts): array
    {
        $rows = new self(
            Collections::firstPerKey($people, keyOf: static fn (Person $person): string => $person->id()->toRfc4122()),
            Collections::firstPerKey($accounts, keyOf: static fn (User $user): string => $user->id()->toRfc4122()),
            AccountReferences::of($accounts),
        );

        return new ArrayCollection($requests)->map($rows->rowOf(...))->getValues();
    }

    /**
     * @param list<DataRequest> $requests
     *
     * @return list<Uuid>
     */
    public static function personIdsOf(array $requests): array
    {
        return Collections::mapPresent(
            new ArrayCollection($requests),
            static fn (DataRequest $request): ?Uuid => $request->subject()->personId,
        )->getValues();
    }

    /**
     * @param list<DataRequest> $requests
     *
     * @return list<Uuid>
     */
    public static function accountIdsOf(array $requests): array
    {
        $shown = new ArrayCollection($requests);

        return [
            ...Collections::mapPresent(
                $shown,
                static fn (DataRequest $request): ?Uuid => $request->subject()->accountId,
            )->getValues(),
            ...Collections::mapPresent(
                $shown,
                static fn (DataRequest $request): ?Uuid => $request->closing()->by,
            )->getValues(),
        ];
    }

    private function rowOf(DataRequest $request): DataRequestRow
    {
        return DataRequestRow::of(
            $request,
            RequestSubjectName::of($request->subject(), $this->people, $this->accounts),
            $this->references->referenceOf($request->closing()->by),
        );
    }
}
