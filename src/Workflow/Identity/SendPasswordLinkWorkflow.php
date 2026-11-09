<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\PasswordLinks;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Workflow\Identity\Model\IssuedPasswordLink;

final readonly class SendPasswordLinkWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private PasswordLinks $links,
    ) {
    }

    public function __invoke(string $accountId, string $actorId): IssuedPasswordLink
    {
        $this->access->require(Action::Update, Resource::Users);

        return new IssuedPasswordLink($this->links->sendToAccount($accountId, $actorId));
    }
}
