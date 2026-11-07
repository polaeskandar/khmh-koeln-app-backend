<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Meetings;

use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Interface\Api\Meetings\ScheduleBodies;
use App\Tests\Support\Interface\Api\Meetings\ScheduleRows;
use App\Tests\Support\JsonBody;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class ScheduleWritesControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAScheduleFromTodayGetsTodaysSessionAndOneFromNextWeekNone(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $rows = new ScheduleRows(self::getContainer());
        [$service] = $rows->structure();
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('POST', '/api/schedules', ScheduleBodies::created($service, null, '2026-11-08'));
        self::assertResponseStatusCodeSame(201);
        $this->assertJsonPath('createdSessions', 1);
        $id = Type::of($this->responseJson()['id'] ?? null)->toString();

        self::assertStringEndsWith(
            '/api/schedules/' . $id,
            (string) $this->browser()->getResponse()->headers->get('Location'),
        );

        self::assertSame('Sunday class', $rows->column($id, 'name'));
        self::assertSame('Sunday class|Hall|12:00:00|13:30:00|expected', $rows->sessionOn($id, '2026-11-08'));

        $this->jsonRequest('POST', '/api/schedules', ScheduleBodies::created($service, null, '2026-11-15'));
        $this->assertJsonPath('createdSessions', 0);
    }

    /**
     * @param array<string, array<string, mixed>> $changes
     * @param array<string, string|int> $params
     */
    #[DataProvider('invalidSchedules')]
    public function testEachInvalidFieldHasItsCode(
        array $changes,
        string $field,
        string $code,
        array $params = [],
    ): void {
        [$service, $stage] = new ScheduleRows(self::getContainer())->structure();
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest(
            'POST',
            '/api/schedules',
            array_replace_recursive(ScheduleBodies::created($service, $stage, '2026-11-08'), $changes),
        );

        $this->assertFieldError($field, $code, $params);
    }

    /**
     * @return iterable<string, array{0: array<string, array<string, mixed>>, 1: string, 2: string, 3?: array<string, string|int>}>
     */
    public static function invalidSchedules(): iterable
    {
        yield 'unknown service' => [
            ['roster' => ['serviceId' => '019a0000-0000-7000-8000-000000000000', 'stageId' => null]],
            'roster.serviceId',
            'meetings.chooseService',
        ];

        yield 'unknown stage' => [
            ['roster' => ['stageId' => '019a0000-0000-7000-8000-000000000000']],
            'roster.stageId',
            'meetings.chooseStage',
        ];

        yield 'no mode' => [
            ['roster' => ['mode' => 'everyone']],
            'roster.mode',
            'meetings.chooseRosterMode',
            ['choices' => '"expected", "open"'],
        ];

        yield 'blank name' => [['details' => ['name' => '   ']], 'details.name', 'meetings.scheduleNameRequired'];
        yield 'long name' => [
            ['details' => ['name' => str_repeat('a', times: 121)]],
            'details.name',
            'validation.tooLong',
            ['max' => 120, 'limit' => 120],
        ];

        yield 'bad time' => [['times' => ['startTime' => '25:00']], 'times.startTime', 'validation.invalidTime'];
        yield 'empty start time' => [['times' => ['startTime' => '']], 'times.startTime', 'validation.chooseTime'];
        yield 'end before start' => [['times' => ['endTime' => '11:00']], 'times.endTime', 'meetings.endAfterStart'];
        yield 'no interval' => [['recurrence' => ['interval' => 0]], 'recurrence.interval', 'recurrence.interval'];
    }

    public function testTheErrorsOfEveryPartAreReportedTogether(): void
    {
        [$service] = new ScheduleRows(self::getContainer())->structure();
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $broken = [
            'details' => ['name' => null],
            'recurrence' => ['interval' => 0],
            'times' => ['endTime' => '11:00'],
        ];

        $unknownService = ['roster' => ['serviceId' => Uuid::v7()->toRfc4122()]];

        $body = array_replace_recursive(
            ScheduleBodies::created($service, null, '2026-11-08'),
            $broken,
            $unknownService,
        );

        $this->jsonRequest('POST', '/api/schedules', $body);
        $this->assertBrokenParts('roster.serviceId');

        $this->jsonRequest('POST', '/api/schedules', ScheduleBodies::created($service, null, '2026-11-08'));
        $id = Type::of($this->responseJson()['id'] ?? null)->toString();
        $this->jsonRequest('PUT', '/api/schedules/' . $id, ScheduleBodies::replaced($broken));
        $this->assertBrokenParts();
    }

    public function testAdministratorsCreateAndOthersMayNot(): void
    {
        [$service] = new ScheduleRows(self::getContainer())->structure();
        $body = ScheduleBodies::created($service, null, '2026-11-15');

        $this->jsonRequest('POST', '/api/schedules', $body);
        $this->assertProblem(401, 'auth.unauthenticated');

        $this->signInWithRoles('admin@example.org', 'administrator');
        $this->jsonRequest('POST', '/api/schedules', $body);
        self::assertResponseStatusCodeSame(201);

        foreach (['servant', 'parent'] as $role) {
            $this->signInWithRoles($role . '@example.org', $role);
            $this->jsonRequest('POST', '/api/schedules', $body);
            $this->assertProblem(403, 'forbidden');
        }
    }

    private function assertBrokenParts(string ...$more): void
    {
        self::assertResponseStatusCodeSame(422);

        $fields = array_keys(JsonBody::asArray($this->responseJson()['fieldErrors'] ?? null, '"fieldErrors"'));
        $expected = [...$more, 'details.name', 'recurrence.interval', 'times.endTime'];
        sort($expected);
        sort($fields);
        self::assertSame($expected, $fields);
    }
}
