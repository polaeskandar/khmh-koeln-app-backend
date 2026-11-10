<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\CoveredSchedules;
use App\Context\Meetings\Application\Service\MeetingSchedules;
use App\Context\Meetings\Application\Service\ScheduleWrites;
use App\Library\Problem\Model\Ids;

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

        $schedule = $this->schedules->get(Ids::toUuid($id, notFound: 'schedule'));

        $this->access->requireIn(
            Action::Update,
            Resource::Sessions,
            CoveredSchedules::groupOf($schedule),
            missing: 'schedule',
        );

        return $this->writes->deactivate($schedule)->id()->toRfc4122();
    }
}
