<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;

final readonly class SessionAbilities
{
    public function __construct(
        public bool $manage,
        public bool $record,
        public bool $reopen,
        public bool $readContent,
    ) {
    }

    public static function grantedBy(AccessCheck $access): self
    {
        $manage = $access->allows(Action::Update, Resource::Sessions);

        return new self(
            manage: $manage,
            record: $access->allows(Action::Create, Resource::Attendance),
            reopen: $manage,
            readContent: $access->allows(Action::Read, Resource::Attendance),
        );
    }
}
