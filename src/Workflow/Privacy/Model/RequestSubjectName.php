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
     * @param ReadableCollection<string, Person> $people
     * @param ReadableCollection<string, User> $accounts
     */
    public static function of(
        RequestSubject $subject,
        ReadableCollection $people,
        ReadableCollection $accounts,
    ): ?string {
        $person = $people->get($subject->personId?->toRfc4122() ?? '');

        if ($person !== null) {
            return $person->name()->firstName . ' ' . $person->name()->lastName;
        }

        return $accounts->get($subject->accountId?->toRfc4122() ?? '')?->profile()->displayName->value;
    }
}
