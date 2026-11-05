<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\People\Application\Service\PeopleExports;
use App\Context\Privacy\Domain\ValueObject\RequestSubject;
use App\Library\Problem\Exception\InvalidInput;
use Symfony\Component\Uid\Uuid;

final readonly class RequestSubjects
{
    public static function of(
        DataRequestDetails $details,
        PeopleExports $people,
        AccountLookup $accounts,
    ): RequestSubject {
        $person = $details->personId === null ? null : $people->person($details->personId);
        $account = $details->accountId === null ? null : $accounts->account(Uuid::fromString($details->accountId));

        if (($person === null) === ($account === null)) {
            throw InvalidInput::field('subject', 'privacy.chooseSubject');
        }

        return new RequestSubject($person?->id(), $account?->id());
    }
}
