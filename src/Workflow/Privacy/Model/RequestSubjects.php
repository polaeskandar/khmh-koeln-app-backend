<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\People\Domain\Entity\Person;
use App\Context\Privacy\Domain\ValueObject\RequestSubject;
use App\Library\Problem\Exception\InvalidInput;

final readonly class RequestSubjects
{
    public static function of(?Person $person, ?User $account): RequestSubject
    {
        if (($person === null) === ($account === null)) {
            throw InvalidInput::field('subject', 'privacy.chooseSubject');
        }

        return new RequestSubject($person?->id(), $account?->id());
    }
}
