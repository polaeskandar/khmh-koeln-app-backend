<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\PlacementChoices;
use App\Context\Scheduling\Application\Service\OneOffSessions;
use App\Context\Scheduling\Domain\Enum\RosterMode;
use App\Context\Scheduling\Domain\ValueObject\OneOffDraft;
use App\Context\Scheduling\Domain\ValueObject\Roster;
use App\Workflow\Organization\Model\StructureGroup;
use App\Workflow\Scheduling\Model\OneOffChoice;
use Symfony\Component\Uid\Uuid;

final readonly class CreateOneOffSessionWorkflow
{
    private const string ROSTER = 'roster.';

    public function __construct(
        private AccessCheck $access,
        private PlacementChoices $placements,
        private OneOffSessions $oneOffs,
    ) {
    }

    public function __invoke(OneOffChoice $oneOff, string $accountId): string
    {
        $this->access->require(Action::Create, Resource::Sessions);

        return $this->placements->whileServicesKept([$oneOff->roster->serviceId], fn (): string => $this->created(
            $oneOff,
            $accountId,
        ));
    }

    private function created(OneOffChoice $oneOff, string $accountId): string
    {
        $roster = $oneOff->roster;
        $chosen = $this->placements->chosen($roster->serviceId, $roster->stageId, fieldPrefix: self::ROSTER);

        $this->access->requireChosen(
            Action::Create,
            Resource::Sessions,
            StructureGroup::ofPlacement($chosen),
            self::ROSTER,
        );

        $draft = new OneOffDraft(
            new Roster($chosen->service->id(), $chosen->stage?->id(), RosterMode::from($roster->mode ?? '')),
            $oneOff->title,
            $oneOff->location,
            startsAt: $oneOff->startsAt ?? '',
            endsAt: $oneOff->endsAt,
        );

        return $this->oneOffs->create($draft, Uuid::fromString($accountId))->id()->toRfc4122();
    }
}
