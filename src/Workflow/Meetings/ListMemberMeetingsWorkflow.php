<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\MemberCheck;
use App\Context\Meetings\Application\Service\MeetingSchedules;
use App\Context\Meetings\Domain\ValueObject\AgendaRange;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\MemberRecords;
use App\Workflow\Meetings\Model\MemberMeetingList;
use App\Workflow\People\Model\MemberRoster;
use Symfony\Component\Uid\Uuid;

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
        $record = $this->records->getOwnRecord($this->member->require(Uuid::fromString($viewerId)));

        $roster = MemberRoster::of(
            $record,
            $this->structure->listServices(MemberRoster::serviceIdsOf($record)),
            $this->structure->listStages(MemberRoster::stageIdsOf($record)),
        );

        $range = AgendaRange::days($roster->today, self::DAYS);
        $mine = $this->schedules->listFor(serviceId: null, stageId: null)->filter($roster->covers(...));
        $archived = ArchivedStructure::of($this->structure->archivedIds());

        return MemberMeetingList::of(
            $range,
            $this->schedules->getAgendaFor($mine, $archived, $range)->withoutCompleted(),
            $roster,
        );
    }
}
