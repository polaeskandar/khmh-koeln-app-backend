<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Meetings;

use App\Context\Organization\Domain\Entity\Service;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use Override;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class SchedulesControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    #[Override]
    protected function setUp(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        parent::setUp();
    }

    public function testNextDatesSkipACanceledDateAndShowTodaysOpenSession(): void
    {
        $service = $this->service('Sunday School');
        $schedules = new Schedules(self::getContainer());
        $bible = $schedules->weekly($service, 'Bible Study', 3);
        $schedules->session($bible, '2026-11-11', "status = 'canceled', cancel_reason = 'Holiday'");
        $schedules->monthly($service, 'Family Saturday', 1, 6);
        $sunday = $schedules->weekly($service, 'Sunday class', 7);
        $open = $schedules->session($sunday, '2026-11-08', "status = 'active', opened_at = created_at");
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('GET', '/api/schedules');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('today', '2026-11-08');
        $this->assertJsonPath('items.0.name', 'Bible Study');
        $this->assertJsonPath('items.0.nextDates.0.date', '2026-11-18');
        $this->assertJsonPath('items.0.nextDates.1.date', '2026-11-25');
        $this->assertJsonPath('items.0.nextDates.2.date', '2026-12-02');
        $this->assertJsonPath('items.0.nextDates.0.status', 'planned');
        $this->assertJsonPath('items.0.sessionCount', 1);
        $this->assertJsonPath('items.1.nextDates.0.date', '2026-12-05');
        $this->assertJsonPath('items.2.nextDates.0.status', 'active');
        $this->assertJsonPath('items.2.nextDates.0.sessionId', $open->id()->toRfc4122());
        $this->assertJsonPath('items.2.service.name', 'Sunday School');
        $this->assertJsonPath('items.2.stage', null);
        self::assertArrayNotHasKey('can', JsonBody::asArray($this->responseJson()['items'][2] ?? null, 'schedule'));
    }

    public function testSchedulesThatGenerateNothingHaveNoNextDates(): void
    {
        $archived = $this->service('Old Club');
        $archived->archive(Structure::now());
        $this->database()->persisted($archived);
        $active = $this->service('Youth');
        $schedules = new Schedules(self::getContainer());
        $schedules->weekly($archived, 'A archived service', 7);
        $schedules->weekly($active, 'B ended', 7, endsOn: '2026-11-01');
        $inactive = $schedules->weekly($active, 'C inactive', 7);

        $this
            ->database()
            ->connection()
            ->executeStatement('UPDATE meetings.meeting_schedule SET active = false WHERE id = ?', [$inactive->id()->toRfc4122()]);

        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('GET', '/api/schedules');

        foreach ([0, 1, 2] as $index) {
            $this->assertJsonPath('items.' . $index . '.generating', false);
            $this->assertJsonPath('items.' . $index . '.nextDates', []);
        }
    }

    public function testOneScheduleFiltersAndUnknownIds(): void
    {
        $youth = $this->service('Youth');
        $choir = $this->service('Choir');
        $schedules = new Schedules(self::getContainer());
        $schedule = $schedules->weekly($youth, 'Youth Meeting', 5);
        $schedules->weekly($choir, 'Choir practice', 4);
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('GET', '/api/schedules?serviceId=' . $youth->id()->toRfc4122());
        $this->assertJsonPath('items.0.name', 'Youth Meeting');
        self::assertCount(1, JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'));

        $this->jsonRequest('GET', '/api/schedules/' . $schedule->id()->toRfc4122());
        $this->assertJsonPath('schedule.recurrence.weekdays', [5]);
        $this->assertJsonPath('schedule.generating', true);

        $this->jsonRequest('GET', '/api/schedules/019a3b6e-0000-7000-8000-000000000001');
        $this->assertProblem(404, 'notFound.schedule');

        $this->jsonRequest('GET', '/api/schedules/not-a-uuid');
        $this->assertProblem(404, 'notFound.schedule');
    }

    public function testFiltersThatMatchNothingAndMalformedIds(): void
    {
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('GET', '/api/schedules?stageId=019a3b6e-0000-7000-8000-000000000002');
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('items', []);

        $this->jsonRequest('GET', '/api/schedules?serviceId=youth');
        $this->assertFieldError('serviceId', 'validation.invalidType');
    }

    private function service(string $name): Service
    {
        $service = Structure::service($name);
        $this->database()->persisted($service);

        return $service;
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
