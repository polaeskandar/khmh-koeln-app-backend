<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\MemberLinkRemovals;

final readonly class UnlinkMemberWorkflow
{
    public function __construct(
        private MemberLinkRemovals $removals,
    ) {
    }

    public function __invoke(string $email): void
    {
        $this->removals->removeByEmail($email);
    }
}
