<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Application\Service\MemberCheck;
use App\Context\Learning\Application\Service\RecentLessons;
use App\Context\Meetings\Application\Service\MeetingSchedules;
use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\MemberRecords;
use App\Workflow\MemberPortal\Model\GroupServants;
use App\Workflow\MemberPortal\Model\MemberGroupList;
use App\Workflow\MemberPortal\Model\MemberRoster;

final readonly class ListMemberGroupsWorkflow
{
    // @mago-expect lint:excessive-parameter-list
    public function __construct(
        private MemberCheck $member,
        private AccountLookup $accounts,
        private MemberRecords $records,
        private StructureLookup $structure,
        private MeetingSchedules $schedules,
        private RecentLessons $lessons,
    ) {
    }

    public function __invoke(string $viewerId): MemberGroupList
    {
        $roster = MemberRoster::lookedUpIn($this->records, $this->structure, $this->member->require($viewerId));

        return MemberGroupList::of(
            $roster,
            $this->schedules
                ->recurring(serviceId: null, stageId: null)
                ->filter(static fn (Schedule $schedule): bool => $schedule->isActive()),
            GroupServants::lookedUpIn($this->accounts),
            $this->lessons->lastTaughtIn($roster->stageIds(), $roster->today),
        );
    }
}
