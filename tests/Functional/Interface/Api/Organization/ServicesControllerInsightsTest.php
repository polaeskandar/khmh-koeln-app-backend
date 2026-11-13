<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Organization;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\Scheduling\Domain\Entity\Schedules;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use App\Tests\Support\Logs;

final class ServicesControllerInsightsTest extends ApiTestCase
{
    public function testLeadersAreTheEnabledServiceLeaders(): void
    {
        $service = Structure::service();
        new Database(self::getContainer())->persisted($service);
        $accounts = new SignInAccounts(self::getContainer());
        $roles = new RoleGrants(self::getContainer());
        $roles->grant($accounts->add('christine@example.org', ['display_name' => 'Christine Adel']), 'service_leader');

        $roles->grant($accounts->add('gone@example.org', [
            'display_name' => 'Gone Leader',
            'disabled_at' => '2026-11-01 10:00:00+01',
        ]), 'service_leader');

        $roles->grant($accounts->add('daniel@example.org', ['display_name' => 'Daniel Michael']), 'servant');
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('servant@example.org', 'servant'));

        $this->jsonRequest('GET', '/api/service-leaders');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('items', [['id' => $this->idOf('christine@example.org'), 'name' => 'Christine Adel']]);
    }

    public function testTwentyServicesCostAsManyStatementsAsOne(): void
    {
        $schedules = new Schedules(self::getContainer());
        $first = Structure::service('Service 0');
        new Database(self::getContainer())->persisted($first);
        $schedules->weekly($first, 'Meeting 0', 7);

        $servant = new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'servant@example.org',
            'servant',
        ));

        $this->jsonRequest('GET', '/api/services/insights');
        $few = \count(new Logs(self::getContainer())->containing('SELECT'));

        foreach (range(start: 1, end: 19) as $n) {
            $service = Structure::service('Service ' . $n);
            new Database(self::getContainer())->persisted($service);
            $schedules->weekly($service, 'Meeting ' . $n, 1 + ($n % 7));
        }

        new ServedGroups(self::getContainer())->everyService($servant);

        $this->jsonRequest('GET', '/api/services/insights');
        self::assertCount(20, JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'));
        self::assertCount($few, new Logs(self::getContainer())->containing('SELECT'));
    }

    public function testParentsAreRefusedAndAnUnknownStatusIsAFieldError(): void
    {
        $this->signInWithRoles('parent@example.org', 'parent');

        foreach (['/api/services/insights', '/api/service-leaders'] as $path) {
            $this->jsonRequest('GET', $path);
            $this->assertProblem(403, 'forbidden');
        }

        $this->browser()->getCookieJar()->clear();
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('servant@example.org', 'servant'));
        $this->jsonRequest('GET', '/api/services/insights?status=closed');
        $this->assertFieldError('status', 'validation.invalidChoice');
    }

    private function idOf(string $email): string
    {
        $id = new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT id FROM identity.app_user WHERE username = ?', [Accounts::usernameOf($email)]);

        self::assertIsString($id);

        return $id;
    }
}
