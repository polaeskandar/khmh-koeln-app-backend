<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\ValueObject\NamedAccounts;
use App\Context\People\Domain\Entity\Person;
use App\Context\Privacy\Domain\ValueObject\RequestSubject;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class RequestSubjectName
{
    /**
     * @param ReadableCollection<non-empty-string, Person> $people
     */
    public static function of(RequestSubject $subject, ReadableCollection $people, NamedAccounts $accounts): ?string
    {
        $person = $subject->personId === null ? null : $people->get($subject->personId->toRfc4122());

        if ($person !== null) {
            return $person->name()->fullName();
        }

        $account = $accounts->find($subject->accountId);

        return $account === null ? null : $accounts->nameOf($account);
    }
}
