<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Scheduling;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Context\Scheduling\Domain\Entity\Schedules;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use App\Tests\Support\Logs;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class SchedulesControllerCountsTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testRosterSizesCountTodaysRosterAndListAndDetailAgree(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $service = Structure::service();
        $third = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $third);
        $rosters = new Rosters(self::getContainer());

        foreach (range(start: 1, end: 14) as $n) {
            $rosters->place($rosters->person('Member', 'Number ' . $n), $service, $third, '2026-09-01');
        }

        $rosters->place($rosters->person('Tomorrow', 'Joiner'), $service, $third, '2026-11-09');
        $rosters->place($rosters->person('Service', 'Helper'), $service, null, '2026-09-01');
        $schedules = new Schedules(self::getContainer());
        $class = $schedules->weekly($service, 'Sunday class', 7, stage: $third);
        $schedules->weekly($service, 'Service meeting', 3);
        $open = $schedules->weekly($service, 'Open evening', 6);

        new Database(self::getContainer())
            ->connection()
            ->update('scheduling.schedule', ['roster_mode' => 'open'], ['id' => $open->id()->toRfc4122()]);

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('servant@example.org', 'servant'));

        $this->jsonRequest('GET', '/api/schedules');

        self::assertResponseIsSuccessful();
        self::assertSame(['Open evening' => null, 'Service meeting' => 1, 'Sunday class' => 14], $this->rosterSizes());

        $this->jsonRequest('GET', '/api/schedules/' . $class->id()->toRfc4122());
        $this->assertJsonPath('schedule.rosterSize', 14);
    }

    public function testMoreSchedulesCostNoMoreStatements(): void
    {
        $service = Structure::service();
        new Database(self::getContainer())->persisted($service);
        $schedules = new Schedules(self::getContainer());
        $schedules->weekly($service, 'Meeting 0', 1);
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('servant@example.org', 'servant'));
        $this->jsonRequest('GET', '/api/schedules');
        $few = \count(new Logs(self::getContainer())->containing('SELECT'));

        foreach (range(start: 1, end: 19) as $n) {
            $schedules->weekly($service, 'Meeting ' . $n, 1 + ($n % 7));
        }

        $this->jsonRequest('GET', '/api/schedules');
        self::assertCount(20, JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'));
        self::assertCount($few, new Logs(self::getContainer())->containing('SELECT'));
    }

    /**
     * @return array<string, int|null>
     */
    private function rosterSizes(): array
    {
        $items = array_map(
            static fn (mixed $item): array => JsonBody::asArray($item, 'item'),
            JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'),
        );

        $sizes = array_map(
            static fn (array $item): ?int => \is_int($item['rosterSize'] ?? null) ? $item['rosterSize'] : null,
            array_column($items, column_key: null, index_key: 'name'),
        );

        ksort($sizes);

        return array_combine(array_map(strval(...), array_keys($sizes)), $sizes);
    }
}
