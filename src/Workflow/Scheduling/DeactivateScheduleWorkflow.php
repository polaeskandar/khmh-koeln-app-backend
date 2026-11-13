<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Scheduling\Application\Service\CoveredSchedules;
use App\Context\Scheduling\Application\Service\MeetingSchedules;
use App\Context\Scheduling\Application\Service\ScheduleWrites;
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
