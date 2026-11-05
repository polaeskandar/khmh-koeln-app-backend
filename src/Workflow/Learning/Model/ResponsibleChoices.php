<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Domain\ValueObject\ResponsibleChoice;
use Symfony\Component\Uid\Uuid;

final readonly class ResponsibleChoices
{
    public static function lookedUpIn(AccountLookup $accounts, ?string $sent): ResponsibleChoice
    {
        $isPreparer =
            $sent !== null
            && Uuid::isValid($sent)
            && $accounts->isActiveAndAllowedTo(Uuid::fromString($sent), Action::Update, Resource::Lessons);

        return new ResponsibleChoice($sent, $isPreparer);
    }
}
