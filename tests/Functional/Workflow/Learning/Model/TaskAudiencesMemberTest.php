<?php

declare(strict_types=1);

namespace App\Tests\Functional\Workflow\Learning\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Learning\Application\Service\HomeworkReports;
use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\StageRosters;
use App\Tests\Support\Context\Learning\Domain\Entity\CopticCourse;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Workflow\Learning\Model\AudienceCounts;
use App\Workflow\Learning\Model\AudienceRow;
use App\Workflow\Learning\Model\TaskAudiences;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class TaskAudiencesMemberTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    #[Override]
    protected function setUp(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
    }

    public function testOwnAccountsAreShownAndOnlyPeopleWithoutAnyAccountCount(): void
    {
        $course = CopticCourse::seeded(self::getContainer());
        $course->member('hoda@example.org', 'Hoda');
        $course->member('nadia@example.org', 'Nadia');
        $nevine = $course->member('nevine@example.org', 'Nevine');
        $rosters = new Rosters(self::getContainer());

        foreach (['Abanoub', 'Bishoy', 'Carina', 'David', 'Ereny', 'Fady'] as $child) {
            $rosters->place($rosters->person($child, 'Boulos'), $course->course, $course->level1, '2026-09-01');
        }

        $task = $course->task('letters');
        $own = array_column(array_map(self::ownAccountOf(...), $this->audiences($task)->rowsOf($task)), 1, 0);
        ksort($own);

        self::assertSame(
            [
                'Abanoub' => false,
                'Bishoy' => false,
                'Carina' => false,
                'David' => false,
                'Ereny' => false,
                'Fady' => false,
                'Hoda' => true,
                'Nadia' => true,
                'Nevine' => true,
            ],
            $own,
        );

        self::assertSame([9, 6], self::reach($this->audiences($task)->countsOf($task)));

        $course->report('letters', $nevine, 'nevine@example.org');
        $nevineRow = $this->row('Nevine', $task);
        self::assertTrue($nevineRow->report->bySelf ?? false);
        self::assertSame('2026-11-01T10:00:00+01:00', $nevineRow->report?->completedAt);
    }

    public function testParentReportsDisabledAccountsAndReportsWithoutAccountsAreTold(): void
    {
        $course = CopticCourse::seeded(self::getContainer());
        $youssef = $course->member('youssef@example.org', 'Youssef');
        $course->member('disabled@example.org', 'Disabled');
        $families = new Families(self::getContainer());
        $families->link($families->account('hoda@example.org'), $youssef);
        $course->report('letters', $youssef, 'hoda@example.org');
        $rosters = new Rosters(self::getContainer());
        $reported = $rosters->person('Reported', 'Boulos');
        $rosters->place($reported, $course->course, $course->level1, '2026-09-01');
        $course->report('letters', $reported, 'hoda@example.org');

        new Database(self::getContainer())
            ->connection()
            ->update(
                'identity.app_user',
                ['disabled_at' => '2026-11-01 10:00:00+01'],
                ['email' => 'disabled@example.org'],
            );

        $task = $course->task('letters');

        self::assertFalse($this->row('Youssef', $task)->report->bySelf ?? true);
        self::assertFalse($this->row('Disabled', $task)->ownAccount);
        self::assertSame(1, $this->audiences($task)->countsOf($task)->open->withoutAccount);

        $samaritan = $course->task('samaritan');
        [$withoutParent, $withoutAny] = self::reach($this->audiences($samaritan)->countsOf($samaritan));
        self::assertSame($withoutParent, $withoutAny);
    }

    public function testAMemberWhoReportedAndLeftKeepsTheirOwnAccountOnTheRow(): void
    {
        $course = CopticCourse::seeded(self::getContainer());
        $leaver = $course->member('leaver@example.org', 'Leaver', until: '2026-11-01');
        $course->report('letters', $leaver, 'leaver@example.org');
        $task = $course->task('letters');

        $row = $this->row('Leaver', $task);

        self::assertFalse($row->inStage);
        self::assertTrue($row->ownAccount);
        self::assertTrue($row->report->bySelf ?? false);
        self::assertSame([0, 0], self::reach($this->audiences($task)->countsOf($task)));
    }

    /**
     * @return array{string, bool}
     */
    private static function ownAccountOf(AudienceRow $row): array
    {
        return [$row->person->firstName, $row->ownAccount];
    }

    /**
     * @return array{int, int}
     */
    private static function reach(AudienceCounts $counts): array
    {
        return [$counts->open->withoutParentAccount, $counts->open->withoutAccount];
    }

    private function row(string $firstName, HomeworkAssignment $task): AudienceRow
    {
        $rows = array_filter(
            $this->audiences($task)->rowsOf($task),
            static fn (AudienceRow $row): bool => $row->person->firstName === $firstName,
        );

        return array_values($rows)[0] ?? self::fail('No row for ' . $firstName . '.');
    }

    private function audiences(HomeworkAssignment $task): TaskAudiences
    {
        return TaskAudiences::lookedUpIn(
            self::service(StructureLookup::class),
            self::service(StageRosters::class),
            self::service(AccountLookup::class),
            self::service(HomeworkReports::class)->of([$task]),
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function service(string $class): object
    {
        $service = self::getContainer()->get($class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
