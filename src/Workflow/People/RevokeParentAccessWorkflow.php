<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\People\Application\Service\ParentAccess;
use App\Library\Problem\Model\Ids;
use Symfony\Component\Uid\Uuid;

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

        $this->parentAccess->revoke(Ids::toUuid($id, notFound: 'guardian'), Uuid::fromString($accountId));
    }
}
