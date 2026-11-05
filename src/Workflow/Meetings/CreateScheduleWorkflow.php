<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\ScheduleWrites;
use App\Context\Meetings\Domain\Enum\RosterMode;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Meetings\Domain\ValueObject\Roster;
use App\Context\Meetings\Domain\ValueObject\ScheduleDraft;
use App\Context\Organization\Application\Service\PlacementChoices;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Workflow\Meetings\Model\NewSchedule;
use App\Workflow\Meetings\Model\WrittenSchedule;

final readonly class CreateScheduleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private PlacementChoices $placements,
        private StructureLookup $structure,
        private ScheduleWrites $writes,
    ) {
    }

    public function __invoke(NewSchedule $schedule): WrittenSchedule
    {
        $this->access->require(Action::Create, Resource::Sessions);

        $roster = $schedule->roster;
        $chosen = $this->placements->of($roster->serviceId, $roster->stageId, fieldPrefix: 'roster.');

        return WrittenSchedule::of($this->writes->create(
            new ScheduleDraft(
                new Roster($chosen->service->id(), $chosen->stage?->id(), RosterMode::from($roster->mode ?? '')),
                $schedule->details->draft(),
                $schedule->recurrence->draft(),
                $schedule->times->draft(),
            ),
            ArchivedStructure::of($this->structure->archivedIds()),
        ));
    }
}
