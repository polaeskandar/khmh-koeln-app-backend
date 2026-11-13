<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling;

use App\Context\Identity\Application\Service\MemberCheck;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\MemberRecords;
use App\Context\Scheduling\Application\Service\MeetingSchedules;
use App\Context\Scheduling\Domain\ValueObject\AgendaRange;
use App\Context\Scheduling\Domain\ValueObject\ArchivedStructure;
use App\Workflow\People\Model\MemberRoster;
use App\Workflow\Scheduling\Model\MemberMeetingList;
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
