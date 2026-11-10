<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Organization;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\Logs;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class StructureLifecycleControllerLocksTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string SHARED_SERVICE = 'FROM organization.service WHERE id IN (?) ORDER BY id FOR SHARE';

    public function testAMembershipIsAddedUnderAShareLockOnItsService(): void
    {
        [$school, $third] = $this->structure();
        $mina = new Rosters(self::getContainer())->person('Mina');

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'leader@example.org',
            'service_leader',
        ));

        $this->jsonRequest('POST', '/api/people/' . $mina->id()->toRfc4122() . '/memberships', [
            'serviceId' => $school->id()->toRfc4122(),
            'stageId' => $third->id()->toRfc4122(),
        ]);

        self::assertResponseIsSuccessful();
        $this->assertLockedBefore(self::SHARED_SERVICE, 'INSERT INTO people.membership');
    }

    public function testAScheduleIsCreatedUnderAShareLockOnItsService(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$school, $third] = $this->structure();

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'leader@example.org',
            'service_leader',
        ));

        $this->jsonRequest('POST', '/api/schedules', [
            'roster' => [
                'serviceId' => $school->id()->toRfc4122(),
                'stageId' => $third->id()->toRfc4122(),
                'mode' => 'expected',
            ],
            'details' => ['name' => 'Sunday class', 'location' => 'Hall'],
            'recurrence' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => [7],
                'span' => ['startsOn' => '2026-11-08'],
            ],
            'times' => ['startTime' => '12:00', 'endTime' => '13:30'],
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertLockedBefore(self::SHARED_SERVICE, 'INSERT INTO meetings.meeting_schedule');
    }

    public function testALessonIsCreatedUnderAShareLockOnItsStagesService(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [, $third] = $this->structure();

        $leader = new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'leader@example.org',
            'service_leader',
        ));

        $this->jsonRequest('POST', '/api/stages/' . $third->id()->toRfc4122() . '/lessons', [
            'details' => ['title' => 'The Good Samaritan'],
            'meeting' => ['date' => '2026-11-15'],
            'responsibleId' => $leader,
        ]);

        self::assertResponseIsSuccessful();
        $this->assertLockedBefore(self::SHARED_SERVICE, 'INSERT INTO learning.lesson');
    }

    public function testADeletionLocksTheServiceThenItsSchedules(): void
    {
        [$school] = $this->structure();
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('DELETE', '/api/services/' . $school->id()->toRfc4122());

        self::assertResponseStatusCodeSame(204);

        $this->assertLockedBefore(
            'FROM organization.service WHERE id = ? FOR UPDATE',
            'FROM meetings.meeting_schedule',
        );
    }

    private function assertLockedBefore(string $lock, string $statement): void
    {
        $lines = new Logs(self::getContainer())->containing('');

        $firstOf = static fn (string $needle): int => min([
            \PHP_INT_MAX,
            ...array_keys(array_filter($lines, static fn (string $line): bool => str_contains($line, $needle))),
        ]);

        self::assertLessThan(\PHP_INT_MAX, $firstOf($lock));
        self::assertLessThan($firstOf($statement), $firstOf($lock));
    }

    /**
     * @return array{Service, Stage}
     */
    private function structure(): array
    {
        $school = Structure::service();
        $third = Structure::stage($school);
        new Database(self::getContainer())->persisted($school, $third);

        return [$school, $third];
    }
}
