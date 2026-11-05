<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Domain\Entity\Stage;

final readonly class StageAbilities
{
    public function __construct(
        public bool $manageStructure,
        public bool $manageMembers,
    ) {
    }

    public static function of(Stage $stage, AccessCheck $access): self
    {
        $open = !$stage->service()->isArchived();

        return new self(
            manageStructure: $open && $access->allows(Action::Update, Resource::Stages),
            manageMembers: $open && !$stage->isArchived() && $access->allows(Action::Update, Resource::Members),
        );
    }
}
