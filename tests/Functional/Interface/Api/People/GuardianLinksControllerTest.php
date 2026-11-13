<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\People;

use App\Library\Types\Model\Integers;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class GuardianLinksControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string PATH = '/api/guardian-links';

    public function testALinkGivesTheParentRoleOnceAndThePortalListsTheChild(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $accounts = new SignInAccounts(self::getContainer());
        $hoda = $accounts->add('hoda@example.org', ['display_name' => 'Hoda Mikhail']);
        [$kiro, $mina] = $this->children();
        $admin = $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', self::PATH, ['userId' => $hoda, 'personId' => $kiro]);

        self::assertResponseStatusCodeSame(201);
        $this->assertJsonPath('relationship', 'parent');
        $this->assertJsonPath('parent', ['id' => $hoda, 'name' => 'Hoda Mikhail', 'email' => 'hoda@example.org']);
        $this->assertJsonPath('child.id', $kiro);
        $this->assertJsonPath('child.groups.0.stage.name', '3rd Class');
        $this->assertJsonPath('approvedAt', '2026-11-08T10:00:00+01:00');
        $this->assertJsonPath('approvedBy.id', $admin);

        $this->jsonRequest('POST', self::PATH, ['userId' => $hoda, 'personId' => $mina, 'relationship' => 'guardian']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(1, $this->parentRoles($hoda));

        $this->jsonRequest('GET', self::PATH);
        self::assertSame(['Kiro', 'Mina'], $this->childNames());

        $this->browser()->getCookieJar()->clear();
        $this->signInAs('hoda@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/parent/children');
        $this->assertJsonPath('items.0.id', $kiro);
    }

    public function testTheSamePairIsRefusedAlsoWhenAnInsertRacesTheCheck(): void
    {
        $hoda = new SignInAccounts(self::getContainer())->add('hoda@example.org', ['display_name' => 'Hoda Mikhail']);
        [$kiro, $mina] = $this->children();
        $this->racingInserts();
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', self::PATH, ['userId' => $hoda, 'personId' => $mina]);

        $this->assertProblem(
            409,
            'guardians.alreadyHasAccess',
            ['parent' => 'Hoda Mikhail', 'child' => 'Mina Iskander'],
        );

        new Database(self::getContainer())
            ->connection()
            ->executeStatement('DROP TRIGGER guardian_race ON people.guardian_relationship');

        $this->jsonRequest('POST', self::PATH, ['userId' => $hoda, 'personId' => $kiro]);
        $this->jsonRequest('POST', self::PATH, ['userId' => $hoda, 'personId' => $kiro]);

        $this->assertProblem(
            409,
            'guardians.alreadyHasAccess',
            ['parent' => 'Hoda Mikhail', 'child' => 'Kiro Iskander'],
        );
    }

    public function testInactiveAndUnknownPartiesAreRefused(): void
    {
        $accounts = new SignInAccounts(self::getContainer());
        $disabled = $accounts->add('disabled@example.org', ['disabled_at' => '2026-11-01 10:00:00+01']);
        $hoda = $accounts->add('hoda@example.org');
        [$kiro] = $this->children();
        $left = new Families(self::getContainer())->child('Bishoy');

        new Database(self::getContainer())
            ->connection()
            ->update('people.person', ['status' => 'inactive'], ['id' => $left->id()->toRfc4122()]);

        $this->signInWithRoles('admin@example.org', 'administrator');

        foreach ([$disabled] as $account) {
            $this->jsonRequest('POST', self::PATH, ['userId' => $account, 'personId' => $kiro]);
            $this->assertProblem(409, 'guardians.accountInactive');
        }

        $this->jsonRequest('POST', self::PATH, ['userId' => $hoda, 'personId' => $left->id()->toRfc4122()]);
        $this->assertFieldError('personId', 'accounts.childNotFound');
        $this->jsonRequest('POST', self::PATH, ['userId' => Uuid::v7()->toRfc4122(), 'personId' => $kiro]);
        $this->assertProblem(404, 'notFound.user');
        $this->jsonRequest('POST', self::PATH, ['userId' => $hoda, 'personId' => Uuid::v7()->toRfc4122()]);
        $this->assertProblem(404, 'notFound.person');
        $this->jsonRequest('POST', self::PATH, ['userId' => $hoda, 'personId' => $kiro, 'relationship' => 'aunt']);
        $this->assertFieldError('relationship', 'validation.invalidChoice', ['choices' => '"parent", "guardian"']);

        $this->jsonRequest('GET', self::PATH);
        self::assertSame(['admin@example.org', 'hoda@example.org'], $this->accountEmails());
    }

    public function testOnlyAdministratorsManageLinks(): void
    {
        $this->jsonRequest('GET', self::PATH);
        $this->assertProblem(401, 'auth.unauthenticated');

        foreach (['service_leader', 'servant'] as $role) {
            $this->browser()->getCookieJar()->clear();
            $this->signInWithRoles($role . '@example.org', $role);
            $this->jsonRequest('GET', self::PATH);
            $this->assertProblem(403, 'forbidden');

            $this->jsonRequest('POST', self::PATH, [
                'userId' => Uuid::v7()->toRfc4122(),
                'personId' => Uuid::v7()->toRfc4122(),
            ]);

            $this->assertProblem(403, 'guardians.adminOnlyGrant');
            $this->jsonRequest('POST', self::PATH . '/' . Uuid::v7()->toRfc4122() . '/revoke', []);
            $this->assertProblem(403, 'guardians.adminOnlyRemove');
        }
    }

    /**
     * @return array{string, string}
     */
    private function children(): array
    {
        $service = Structure::service();
        $third = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $third);
        $rosters = new Rosters(self::getContainer());
        $kiro = $rosters->person('Kiro');
        $mina = $rosters->person('Mina');
        $rosters->place($kiro, $service, $third, '2026-09-01');

        return [$kiro->id()->toRfc4122(), $mina->id()->toRfc4122()];
    }

    private function racingInserts(): void
    {
        $connection = new Database(self::getContainer())->connection();

        $connection->executeStatement(<<<'SQL'
            CREATE FUNCTION people.guardian_race() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
              IF pg_trigger_depth() = 1 THEN
                INSERT INTO people.guardian_relationship (id, user_id, person_id, relationship, status, approved_at, created_at)
                VALUES (gen_random_uuid(), NEW.user_id, NEW.person_id, NEW.relationship, 'approved', NEW.approved_at, NEW.created_at);
              END IF;
              RETURN NEW;
            END $$
            SQL);

        $connection->executeStatement(
            'CREATE TRIGGER guardian_race BEFORE INSERT ON people.guardian_relationship FOR EACH ROW EXECUTE FUNCTION people.guardian_race()',
        );
    }

    private function parentRoles(string $accountId): int
    {
        return Integers::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne(
                    "SELECT count(*) FROM identity.user_role ur JOIN identity.role r ON r.id = ur.role_id WHERE r.name_key = 'parent' AND ur.user_id = ?",
                    [$accountId],
                ),
        );
    }

    /**
     * @return list<string>
     */
    private function childNames(): array
    {
        return array_values(array_map(
            static fn (mixed $item): string => Type::of(
                JsonBody::asArray(JsonBody::asArray($item, 'item')['child'] ?? null, 'child')['firstName'] ?? null,
            )->toString(),
            JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'),
        ));
    }

    /**
     * @return list<string>
     */
    private function accountEmails(): array
    {
        $emails = array_map(
            static fn (mixed $account): string => Type::of(
                JsonBody::asArray($account, 'account')['email'] ?? null,
            )->toString(),
            JsonBody::asArray($this->responseJson()['accounts'] ?? null, 'accounts'),
        );

        sort($emails);

        return $emails;
    }
}
