<?php

declare(strict_types=1);

namespace App\Workflow\Demo;

use App\Context\Attendance\Application\Service\DemoAttendance;
use App\Context\Identity\Application\Service\DemoAccounts;
use App\Context\Learning\Application\Service\DemoLessons;
use App\Context\Meetings\Application\Service\DemoSchedules;
use App\Context\Meetings\Application\Service\DemoSessions;
use App\Context\Meetings\Domain\ValueObject\HeldSessions;
use App\Context\Organization\Application\Service\DemoStructure;
use App\Context\People\Application\Service\DemoPeople;
use App\Platform\Doctrine\Contract\DemoDatabase;
use App\Workflow\Demo\Model\DemoCast;
use App\Workflow\Demo\Model\DemoGroup;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class SeedDemoDataWorkflow
{
    private const string CLASS_GROUP = '3rd Class';

    private const int PRESENT_LAST_SUNDAY = 11;

    private const int MARKED_TODAY = 7;

    // @mago-expect lint:excessive-parameter-list
    public function __construct(
        private DemoDatabase $database,
        private DemoAccounts $accounts,
        private DemoStructure $structure,
        private DemoPeople $people,
        private DemoSchedules $schedules,
        private DemoSessions $sessions,
        private DemoLessons $lessons,
        private DemoAttendance $attendance,
    ) {
    }

    public function __invoke(): void
    {
        $this->database->replace(function (): void {
            $this->accounts->seed();
            $this->scenario($this->cast());
        });
    }

    private function cast(): DemoCast
    {
        $people = [];
        $schedules = [];

        foreach (DemoGroup::listOf($this->structure->seed()) as $group) {
            $people[$group->name] = $this->people->seed($group->name, $group->serviceId, $group->stageId);
            $schedules[$group->name] = $this->schedules->seed($group->name, $group->serviceId, $group->stageId);
        }

        return new DemoCast($people, $schedules);
    }

    private function scenario(DemoCast $cast): void
    {
        $daniel = $this->accounts->idOf('daniel@example.org');
        $hoda = $this->accounts->idOf('hoda@example.org');
        $mina = $cast->namedOf(self::CLASS_GROUP);
        $mariam = $this->accounts->idOf('mariam@example.org');
        $this->people->giveAccess($hoda, [$mina, $cast->namedOf('1st Class')], approvedBy: $mariam);
        $class = $cast->sundayClassOf(self::CLASS_GROUP);
        $stageId = $class->roster()->stageId ?? throw new LogicException('The demo class has no stage.');
        $this->lessons->seed($stageId, $class->id(), $daniel, $hoda, $mina->id());
        $this->attendanceOf($cast, $this->sessions->hold($cast->schedulesOf(self::CLASS_GROUP), $daniel), $daniel);
    }

    private function attendanceOf(DemoCast $cast, HeldSessions $held, Uuid $servantId): void
    {
        $present = $cast->firstIdsOf(self::CLASS_GROUP, self::PRESENT_LAST_SUNDAY);
        $this->attendance->markAll($held->closedId, $present, $held->closedOpenedAt, $servantId);

        $this->attendance->markRemoved(
            $held->closedId,
            $cast->namedOf('Youth Meeting')->id(),
            $held->closedOpenedAt->modify('+15 minutes'),
            $servantId,
            'Marked in the wrong group',
        );

        $marked = $cast->firstIdsOf(self::CLASS_GROUP, self::MARKED_TODAY);
        $this->attendance->markAll($held->openId, $marked, $held->openOpenedAt, $servantId);
    }
}
