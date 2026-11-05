<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\People\Domain\Entity\Person;
use App\Context\Privacy\Domain\ValueObject\RequestSubject;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class RequestSubjectName
{
    /**
     * @param ReadableCollection<non-empty-string, Person> $people
     * @param ReadableCollection<non-empty-string, User> $accounts
     */
    public static function of(
        RequestSubject $subject,
        ReadableCollection $people,
        ReadableCollection $accounts,
    ): ?string {
        $person = $subject->personId === null ? null : $people->get($subject->personId->toRfc4122());

        if ($person !== null) {
            return $person->name()->fullName();
        }

        $account = $subject->accountId === null ? null : $accounts->get($subject->accountId->toRfc4122());

        return $account?->profile()->displayName->value;
    }
}
