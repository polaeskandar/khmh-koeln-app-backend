<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\MeetingSchedules;
use App\Context\Meetings\Application\Service\ScheduleWrites;

final readonly class DeactivateScheduleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private MeetingSchedules $schedules,
        private ScheduleWrites $writes,
    ) {
    }

    public function __invoke(string $id): string
    {
        $this->access->require(Action::Update, Resource::Sessions);

        return $this->writes->deactivate($this->schedules->find($id))->id()->toRfc4122();
    }
}
