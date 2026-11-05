<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\PasswordLinks;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Library\Time\Client\Calendar;
use App\Workflow\Identity\Model\IssuedPasswordLink;

final readonly class IssuePasswordLinkWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private PasswordLinks $links,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $accountId, string $actorId): IssuedPasswordLink
    {
        $this->access->require(Action::Update, Resource::Users);

        return new IssuedPasswordLink($this->calendar->formatInstant($this->links->sendToAccount(
            $accountId,
            $actorId,
        )));
    }
}
