<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\ScheduleWrites;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Meetings\Domain\ValueObject\SchedulePlan;
use App\Context\Organization\Application\Service\PlacementChoices;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\Organization\Domain\ValueObject\ChosenPlacement;
use App\Library\Problem\Exception\InvalidInput;
use App\Workflow\Meetings\Model\NewScheduleChoice;
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

    public function __invoke(NewScheduleChoice $schedule): WrittenSchedule
    {
        $this->access->require(Action::Create, Resource::Sessions);

        return $this->placements->whileKept([$schedule->roster->serviceId], fn (): WrittenSchedule => $this->created(
            $schedule,
        ));
    }

    private function created(NewScheduleChoice $schedule): WrittenSchedule
    {
        $roster = $schedule->roster;

        $placementOf = fn (): ChosenPlacement => $this->placements->chosen(
            $roster->serviceId,
            $roster->stageId,
            fieldPrefix: 'roster.',
        );

        $planOf = fn (): SchedulePlan => $this->writes->plan($schedule->draft());
        InvalidInput::all($placementOf, $planOf);
        $chosen = $placementOf();

        return WrittenSchedule::of($this->writes->create(
            $chosen->service->id(),
            $chosen->stage?->id(),
            $planOf(),
            ArchivedStructure::of($this->structure->archivedIds()),
        ));
    }
}
