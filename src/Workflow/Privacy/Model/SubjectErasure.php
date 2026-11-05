<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Application\Service\AccountErasures;
use App\Context\People\Application\Service\PersonErasures;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Context\People\Domain\ValueObject\ErasedElsewhere;
use Symfony\Component\Uid\Uuid;

final readonly class SubjectErasure
{
    /**
     * @param callable(ErasablePerson): ErasedElsewhere $personSweep
     * @param callable(Uuid): int $accountSweep
     */
    public static function of(
        ErasableSubject $subject,
        PersonErasures $people,
        AccountErasures $accounts,
        callable $personSweep,
        callable $accountSweep,
    ): ErasureOutcome {
        $personId = $subject->personId;
        $accountId = $subject->accountId;
        $person = $personId === null ? null : $people->erase($personId->toRfc4122(), $personSweep);
        $links = $accountId === null ? 0 : $people->linksOfAccount($accountId);

        $account = $accountId === null
            ? null
            : $accounts->erase($accountId, static fn (): int => $accountSweep($accountId));

        return new ErasureOutcome($person, $account, $links);
    }
}
