<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\People\Application\Service\ParentAccess;

final readonly class RevokeParentAccessWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ParentAccess $parentAccess,
    ) {
    }

    public function __invoke(string $id, string $accountId): void
    {
        $this->access->require(Action::Update, Resource::Users, refusal: 'guardians.adminOnlyRemove');

        $this->parentAccess->revoke($id, $accountId);
    }
}
