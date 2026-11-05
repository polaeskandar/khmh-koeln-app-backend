<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\SessionTransitions;

final readonly class RenameSessionWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private SessionTransitions $transitions,
    ) {
    }

    public function __invoke(string $id, string $accountId, ?string $title, ?string $location): string
    {
        $this->access->require(Action::Update, Resource::Sessions);

        return $this->transitions->rename($id, $accountId, $title, $location)->id()->toRfc4122();
    }
}
