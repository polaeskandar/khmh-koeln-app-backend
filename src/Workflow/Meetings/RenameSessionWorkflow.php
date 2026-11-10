<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\CoveredSchedules;
use App\Context\Meetings\Application\Service\MeetingSessions;
use App\Context\Meetings\Application\Service\SessionDetailChanges;

final readonly class RenameSessionWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private SessionDetailChanges $details,
        private MeetingSessions $sessions,
    ) {
    }

    public function __invoke(string $id, string $accountId, ?string $title, ?string $location): string
    {
        $this->access->require(Action::Update, Resource::Sessions);
        $group = CoveredSchedules::groupOfSession($this->sessions->find($id));
        $this->access->requireIn(Action::Update, Resource::Sessions, $group, missing: 'session');

        return $this->details->change($id, $accountId, $title, $location)->id()->toRfc4122();
    }
}
