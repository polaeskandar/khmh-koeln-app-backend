<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\OneOffSessions;
use App\Context\Meetings\Domain\Enum\RosterMode;
use App\Context\Meetings\Domain\ValueObject\OneOffDraft;
use App\Context\Meetings\Domain\ValueObject\Roster;
use App\Context\Organization\Application\Service\PlacementChoices;
use App\Workflow\Meetings\Model\OneOffChoice;

final readonly class CreateOneOffSessionWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private PlacementChoices $placements,
        private OneOffSessions $oneOffs,
    ) {
    }

    public function __invoke(OneOffChoice $oneOff, string $accountId): string
    {
        $this->access->require(Action::Create, Resource::Sessions);

        return $this->placements->whileKept([$oneOff->roster->serviceId], fn (): string => $this->created(
            $oneOff,
            $accountId,
        ));
    }

    private function created(OneOffChoice $oneOff, string $accountId): string
    {
        $roster = $oneOff->roster;
        $chosen = $this->placements->of($roster->serviceId, $roster->stageId, fieldPrefix: 'roster.');

        $draft = new OneOffDraft(
            new Roster($chosen->service->id(), $chosen->stage?->id(), RosterMode::from($roster->mode ?? '')),
            $oneOff->title,
            $oneOff->location,
            startsAt: $oneOff->startsAt ?? '',
            endsAt: $oneOff->endsAt,
        );

        return $this->oneOffs->create($draft, $accountId)->id()->toRfc4122();
    }
}
