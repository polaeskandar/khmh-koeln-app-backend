<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal;

use App\Context\Identity\Application\Service\MemberCheck;
use App\Context\Meetings\Application\Service\MeetingSchedules;
use App\Context\Meetings\Domain\ValueObject\AgendaRange;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\MemberRecords;
use App\Workflow\MemberPortal\Model\MemberMeetingList;
use App\Workflow\MemberPortal\Model\MemberRoster;

final readonly class ListMemberMeetingsWorkflow
{
    private const int DAYS = 14;

    public function __construct(
        private MemberCheck $member,
        private MemberRecords $records,
        private StructureLookup $structure,
        private MeetingSchedules $schedules,
    ) {
    }

    public function __invoke(string $viewerId): MemberMeetingList
    {
        $record = $this->records->ownRecord($this->member->require($viewerId));

        $roster = MemberRoster::of(
            $record,
            $this->structure->services(MemberRoster::serviceIdsOf($record)),
            $this->structure->stages(MemberRoster::stageIdsOf($record)),
        );

        $range = AgendaRange::days($roster->today, self::DAYS);
        $mine = $this->schedules->listed(serviceId: null, stageId: null)->filter($roster->covers(...));
        $archived = ArchivedStructure::of($this->structure->archivedIds());

        return MemberMeetingList::of(
            $range,
            $this->schedules->agendaFor($mine, $archived, $range)->withoutCompleted(),
            $roster,
        );
    }
}
