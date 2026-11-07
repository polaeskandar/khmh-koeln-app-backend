<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Auth\Registrations;
use App\Tests\Support\JsonBody;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class AccountRequestsControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string SERVANT = 'daniel@example.org';

    public function testAnAdministratorSeesOnlyPendingAccountsOldestFirstWithTheDocumentedKeys(): void
    {
        $service = Structure::service();
        $youth = Structure::service('Youth');
        new Database(self::getContainer())->persisted($service, $youth);
        self::mockTime('2026-11-08T10:00:00+01:00');
        $this->register(Registrations::parent());
        self::mockTime('2026-11-08T11:00:00+01:00');
        $this->register(Registrations::servant([$youth->id()->toRfc4122(), $service->id()->toRfc4122()]));

        $accounts = new SignInAccounts(self::getContainer());
        $accounts->add('disabled@example.org', ['disabled_at' => '2026-11-08 12:00:00+01']);

        $accounts->add('blocked@example.org', [
            'approved_at' => null,
            'disabled_at' => '2026-11-08 12:00:00+01',
            'created_at' => '2026-11-08 12:00:00+01',
        ]);

        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('GET', '/api/account-requests');

        self::assertResponseIsSuccessful();
        $items = JsonBody::asArray($this->responseJson()['items'] ?? null, 'items');
        self::assertCount(3, $items);
        $servant = JsonBody::asArray($items[1] ?? null, 'servant');
        self::assertSame(['account', 'registeredAt', 'registeredAs', 'note', 'services'], array_keys($servant));
        $this->assertJsonPath('items.0.account.email', 'mariam.hanna@example.org');
        $this->assertJsonPath('items.0.registeredAs', 'parent');
        $this->assertJsonPath('items.0.services', []);
        $this->assertJsonPath('items.1.account.email', self::SERVANT);
        $this->assertJsonPath('items.1.registeredAt', '2026-11-08T11:00:00+01:00');
        $this->assertJsonPath('items.1.note', 'Youth, Thursdays');
        $this->assertJsonPath('items.1.services.0.name', 'Sunday School');
        $this->assertJsonPath('items.1.services.1.name', 'Youth');
        $this->assertJsonPath('items.2.account.email', 'blocked@example.org');
        $this->assertJsonPath('today', '2026-11-08');
    }

    public function testOnlyAccountsThatManageUsersMayListOrDecline(): void
    {
        $this->registerServant();

        foreach (['service_leader', 'servant', 'parent'] as $role) {
            $this->signInWithRoles($role . '@example.org', $role);

            $this->jsonRequest('GET', '/api/account-requests');
            $this->assertProblem(403, 'accounts.adminOnly');

            $this->jsonRequest('POST', '/api/account-requests/' . $this->requestId() . '/decline');
            $this->assertProblem(403, 'accounts.adminOnly');
        }
    }

    public function testWithoutASessionTheListIsUnauthenticated(): void
    {
        $this->jsonRequest('GET', '/api/account-requests');

        $this->assertProblem(401, 'auth.unauthenticated');
    }

    public function testDecliningDeletesTheAccountAndWhatItEnteredSoTheEmailCanRegisterAgain(): void
    {
        $this->registerServant();
        $id = $this->requestId();
        $connection = new Database(self::getContainer())->connection();
        $other = Uuid::v7()->toRfc4122();

        $connection->executeStatement('INSERT INTO organization.registration_service (account_id, service_id) SELECT ?, id FROM organization.service', [
            $other,
        ]);

        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', '/api/account-requests/' . $id . '/decline');

        self::assertResponseStatusCodeSame(204);
        self::assertNull($this->idOf(self::SERVANT));
        $count = 'SELECT count(*) FROM organization.registration_service WHERE account_id = ?';
        self::assertSame(0, $connection->fetchOne($count, [$id]));
        self::assertSame(1, $connection->fetchOne($count, [$other]));

        $this->jsonRequest('POST', '/api/auth/sign-in', [
            'email' => self::SERVANT,
            'password' => SignInAccounts::TYPED,
        ]);

        $this->assertProblem(401, 'auth.invalidCredentials');

        $this->registerServant();
        self::assertNotNull($this->idOf(self::SERVANT));
    }

    public function testAnApprovedAccountIsNotDeclined(): void
    {
        $id = new SignInAccounts(self::getContainer())->add(self::SERVANT);
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', '/api/account-requests/' . $id . '/decline');

        $this->assertProblem(409, 'accounts.alreadyApproved');
        self::assertSame($id, $this->idOf(self::SERVANT));

        $admin = $this->idOf('admin@example.org');
        self::assertIsString($admin);
        $this->jsonRequest('POST', '/api/account-requests/' . $admin . '/decline');
        $this->assertProblem(409, 'accounts.alreadyApproved');
    }

    public function testAnUnknownAccountIsNotFound(): void
    {
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', '/api/account-requests/' . Uuid::v7()->toRfc4122() . '/decline');

        $this->assertProblem(404, 'notFound.user');
        $this->jsonRequest('POST', '/api/account-requests/not-a-uuid/decline');
        $this->assertProblem(404, 'notFound.user');
    }

    /**
     * @param array<string, array<string, mixed>> $body
     */
    private function register(array $body): void
    {
        $this->jsonRequest('POST', '/api/auth/register', $body);
        self::assertResponseStatusCodeSame(202);
    }

    private function registerServant(): void
    {
        $service = new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT id FROM organization.service LIMIT 1');

        if (!is_string($service)) {
            $created = Structure::service();
            new Database(self::getContainer())->persisted($created);
            $service = $created->id()->toRfc4122();
        }

        $this->register(Registrations::servant([$service]));
    }

    private function requestId(): string
    {
        $id = $this->idOf(self::SERVANT);
        self::assertIsString($id);

        return $id;
    }

    private function idOf(string $email): ?string
    {
        $id = new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT id FROM identity.app_user WHERE email = ?', [$email]);

        return is_string($id) ? $id : null;
    }
}
