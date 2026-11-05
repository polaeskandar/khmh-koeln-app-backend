<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Organization;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use App\Tests\Support\Logs;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ServicesControllerMemberCountsTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testListAndDetailCountTodaysMembersInBerlinEachPersonOnce(): void
    {
        self::mockTime('2026-11-07T23:30:00Z');
        $service = Structure::service();
        $third = Structure::stage($service);
        $fourth = Structure::stage($service, '4th Class', position: 1);
        new Database(self::getContainer())->persisted($service, $third, $fourth);
        $rosters = new Rosters(self::getContainer());
        $mina = $rosters->person('Mina');
        $rosters->place($mina, $service, $third, '2026-09-01');
        $rosters->place($mina, $service, $fourth, '2026-09-01');
        $rosters->place($rosters->person('Marina'), $service, null, '2026-09-01');
        $rosters->place($rosters->person('Bishoy'), $service, $third, '2026-09-01', '2026-11-07');
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('GET', '/api/services');
        $this->assertJsonPath('items.0.memberCount', 2);
        $this->assertJsonPath('items.0.serviceLevelMemberCount', 1);

        $this->jsonRequest('GET', '/api/services/' . $service->id()->toRfc4122());
        $this->assertJsonPath('memberCount', 2);
        $this->assertJsonPath('serviceLevelMemberCount', 1);

        $this->jsonRequest('GET', '/api/services/' . $service->id()->toRfc4122() . '/stages');
        $this->assertJsonPath('stages.0.memberCount', 1);
        $this->assertJsonPath('stages.1.memberCount', 1);

        $this->jsonRequest('GET', '/api/stages/' . $third->id()->toRfc4122());
        $this->assertJsonPath('memberCount', 1);
    }

    public function testTheListRunsAsManyStatementsForTwentyServicesAsForOne(): void
    {
        new Database(self::getContainer())->persisted(Structure::service('Service 1'));
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('GET', '/api/services');
        $forOne = count(new Logs(self::getContainer())->containing('SELECT'));
        self::assertGreaterThan(0, $forOne);

        foreach (range(start: 2, end: 20) as $number) {
            new Database(self::getContainer())->persisted(Structure::service('Service ' . $number));
        }

        $this->jsonRequest('GET', '/api/services');

        self::assertCount(20, JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'));
        self::assertCount($forOne, new Logs(self::getContainer())->containing('SELECT'));
    }
}
