<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Attendance\Application\Service\AttendanceErasures;
use App\Context\Identity\Application\Service\AccountErasures;
use App\Context\Learning\Application\Service\LearningErasures;
use App\Context\Meetings\Application\Service\MeetingErasures;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PersonErasures;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Context\People\Domain\ValueObject\ErasedElsewhere;
use App\Context\Privacy\Application\Service\RequestErasures;
use App\Workflow\Privacy\Model\NamedMember;
use App\Workflow\Privacy\Model\PersonErasureCounts;
use App\Workflow\Privacy\Model\PersonSweep;

final readonly class RehearsePersonErasureWorkflow
{
    // @mago-expect lint:excessive-parameter-list
    public function __construct(
        private PersonErasures $people,
        private MeetingErasures $meetings,
        private AttendanceErasures $attendance,
        private LearningErasures $learning,
        private RequestErasures $requests,
        private StructureLookup $structure,
        private AccountErasures $accounts,
    ) {
    }

    public function __invoke(string $personId): PersonErasureCounts
    {
        return PersonErasureCounts::of($this->people->rehearse($personId, $this->sweep(...)));
    }

    private function sweep(ErasablePerson $person): ErasedElsewhere
    {
        $member = NamedMember::of($person, $this->structure);
        $this->accounts->forgetPerson($person->id);

        return PersonSweep::of($member, $this->meetings, $this->attendance, $this->learning, $this->requests);
    }
}
