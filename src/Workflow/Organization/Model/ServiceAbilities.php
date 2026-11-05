<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;

final readonly class ServiceAbilities
{
    public function __construct(
        public bool $edit,
        public bool $archive,
        public bool $manageStructure,
        public bool $manageSessions,
        public bool $manageMembers,
    ) {
    }

    public static function grantedBy(AccessCheck $access): self
    {
        return new self(
            edit: $access->allows(Action::Update, Resource::Services),
            archive: $access->allows(Action::Delete, Resource::Services),
            manageStructure: $access->allows(Action::Update, Resource::Stages),
            manageSessions: $access->allows(Action::Update, Resource::Sessions),
            manageMembers: $access->allows(Action::Update, Resource::Members),
        );
    }
}
