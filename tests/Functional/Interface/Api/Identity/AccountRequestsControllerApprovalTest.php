<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
use App\Context\People\Application\Service\Guardianship;
use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Identity\Registrations;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;

final class AccountRequestsControllerApprovalTest extends ApiTestCase
{
    private const string PARENT = 'mariam.hanna@example.org';

    private const string SERVANT = 'daniel@example.org';

    private const string PARENT_ROLE = '019a3f00-0000-7000-8000-000000000004';

    private const string SERVANT_ROLE = '019a3f00-0000-7000-8000-000000000003';

    public function testAParentGetsTheRolesAndOneLinkPerChildAndCanSignIn(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', Registrations::parent());
        $mina = new Families(self::getContainer())->child('Mina');
        $adminId = $this->signInWithRoles('admin@example.org', 'administrator');
        $mina2 = $mina->id()->toRfc4122();

        $this->approve(self::PARENT, [
            'as' => 'parent',
            'childIds' => [$mina2, strtoupper($mina2)],
            'roleIds' => [self::SERVANT_ROLE, strtoupper(self::SERVANT_ROLE)],
        ]);

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('email', self::PARENT);

        $roles = $this
            ->database()
            ->connection()
            ->fetchFirstColumn(
                'SELECT r.role_id FROM identity.user_role r JOIN identity.app_user u ON u.id = r.user_id WHERE u.email = ? ORDER BY r.role_id',
                [self::PARENT],
            );

        self::assertSame([self::SERVANT_ROLE, self::PARENT_ROLE], $roles);

        $row = $this
            ->database()
            ->connection()
            ->fetchAssociative(
                'SELECT approved_by, registered_as, registration_note, approved_at IS NOT NULL AS approved FROM identity.app_user WHERE email = ?',
                [self::PARENT],
            );

        self::assertSame(
            ['approved_by' => $adminId, 'registered_as' => null, 'registration_note' => null, 'approved' => true],
            $row,
        );

        self::assertSame(1, $this
            ->database()
            ->connection()
            ->fetchOne("SELECT count(*) FROM people.guardian_relationship WHERE person_id = ? AND status = 'approved' AND approved_by = ?", [
                $mina2,
                $adminId,
            ]));

        $parent = $this->accounts()->findByEmail(self::PARENT);
        self::assertNotNull($parent);

        $children = $this
            ->guardianship()
            ->approvedOf($parent->id())
            ->map(static fn (GuardianRelationship $link): string => $link->child()->id()->toRfc4122());

        self::assertSame([$mina2], $children->getValues());
        $this->signInAs(self::PARENT, SignInAccounts::TYPED);
    }

    public function testAServantGetsTheChosenRolesEverywhereAndTheNamedServicesAreForgotten(): void
    {
        $service = Structure::service();
        $this->database()->persisted($service);
        $this->jsonRequest('POST', '/api/auth/register', Registrations::servant([$service->id()->toRfc4122()]));
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->approve(self::SERVANT, ['as' => 'servant', 'roleIds' => [self::SERVANT_ROLE, self::SERVANT_ROLE]]);

        self::assertResponseIsSuccessful();

        self::assertSame(
            0,
            $this->database()->connection()->fetchOne('SELECT count(*) FROM organization.registration_service'),
        );

        $this->signInAs(self::SERVANT, SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/services');
        self::assertResponseIsSuccessful();
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string> $children
     * @param array<string, string|int> $params
     */
    #[DataProvider('invalidApprovals')]
    public function testAnInvalidChoiceStoresNothing(
        array $body,
        array $children,
        string $field,
        string $code,
        array $params = [],
    ): void {
        $this->jsonRequest('POST', '/api/auth/register', Registrations::parent());
        $inactive = new Families(self::getContainer())->child('Bishoy');

        $this
            ->database()
            ->connection()
            ->update('people.person', ['status' => 'inactive'], ['id' => $inactive->id()->toRfc4122()]);

        $this->signInWithRoles('admin@example.org', 'administrator');
        $ids = ['inactive' => $inactive->id()->toRfc4122(), 'unknown' => Uuid::v7()->toRfc4122()];

        $this->approve(self::PARENT, [
            ...$body,
            'childIds' => array_map(static fn (string $id): string => $ids[$id] ?? $id, $children),
        ]);

        $this->assertFieldError($field, $code, $params);

        self::assertSame(
            ['approved_at' => null, 'roles' => 0, 'links' => 0],
            $this
                ->database()
                ->connection()
                ->fetchAssociative(<<<'SQL'
                    SELECT u.approved_at,
                      (SELECT count(*) FROM identity.user_role r WHERE r.user_id = u.id) AS roles,
                      (SELECT count(*) FROM people.guardian_relationship g WHERE g.user_id = u.id) AS links
                    FROM identity.app_user u WHERE u.email = ?
                    SQL, [self::PARENT]),
        );
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: list<string>, 2: string, 3: string, 4?: array<string, string|int>}>
     */
    public static function invalidApprovals(): iterable
    {
        $parent = ['as' => 'parent', 'roleIds' => [self::PARENT_ROLE]];
        $servant = ['as' => 'servant'];

        yield 'unknown kind' => [
            ['as' => 'visitor', 'roleIds' => [self::PARENT_ROLE]],
            [],
            'as',
            'validation.invalidChoice',
            ['choices' => '"parent", "servant", "member"'],
        ];

        yield 'no role' => [[...$servant, 'roleIds' => []], [], 'roleIds', 'roles.chooseRole'];
        yield 'unknown role' => [
            [...$servant, 'roleIds' => [self::PARENT_ROLE, 'unknown']],
            [],
            'roleIds',
            'roles.unknown',
        ];

        yield 'no child' => [$parent, [], 'childIds', 'accounts.chooseChild'];
        yield 'no role and no child' => [['as' => 'parent', 'roleIds' => []], [], 'childIds', 'accounts.chooseChild'];
        yield 'unknown child' => [$parent, ['unknown'], 'childIds', 'accounts.childNotFound'];
        yield 'inactive child' => [$parent, ['inactive'], 'childIds', 'accounts.childNotFound'];
        yield 'too many children' => [
            $parent,
            array_map(static fn (): string => Uuid::v7()->toRfc4122(), range(start: 1, end: 21)),
            'childIds',
            'validation.tooMany',
            ['max' => 20],
        ];
    }

    public function testApprovingTwiceIsRefusedAndOnlyAccountsThatManageUsersMayApprove(): void
    {
        $id = new SignInAccounts(self::getContainer())->add(self::SERVANT, ['approved_at' => null]);
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->approve(self::SERVANT, ['as' => 'servant', 'roleIds' => [self::SERVANT_ROLE]]);
        $this->assertProblem(403, 'accounts.adminOnly');

        $this->signInWithRoles('admin@example.org', 'administrator');
        $this->approve(self::SERVANT, ['as' => 'servant', 'roleIds' => [self::SERVANT_ROLE]]);
        self::assertResponseIsSuccessful();

        $this->jsonRequest('POST', '/api/account-requests/' . $id . '/approve', [
            'as' => 'servant',
            'roleIds' => [self::SERVANT_ROLE],
        ]);

        $this->assertProblem(409, 'accounts.alreadyApproved');
    }

    public function testAMissingOrDisabledRequestIsRefusedBeforeTheChoiceIsChecked(): void
    {
        new SignInAccounts(self::getContainer())->add(self::SERVANT, [
            'approved_at' => null,
            'disabled_at' => '2026-11-08 12:00:00+01',
        ]);

        $this->signInWithRoles('admin@example.org', 'administrator');

        foreach (['not-a-uuid', Uuid::v7()->toRfc4122()] as $id) {
            $this->jsonRequest('POST', '/api/account-requests/' . $id . '/approve', [
                'as' => 'servant',
                'roleIds' => [],
            ]);

            $this->assertProblem(404, 'notFound.user');
        }

        $this->approve(self::SERVANT, ['as' => 'servant', 'roleIds' => [self::SERVANT_ROLE]]);
        $this->assertProblem(409, 'accounts.requestDisabled');
    }

    /**
     * @param array<string, mixed> $body
     */
    private function approve(string $email, array $body): void
    {
        $id = $this->database()->connection()->fetchOne('SELECT id FROM identity.app_user WHERE email = ?', [$email]);
        self::assertIsString($id);

        $this->jsonRequest('POST', '/api/account-requests/' . $id . '/approve', $body);
    }

    private function accounts(): AccountRepositoryInterface
    {
        $accounts = self::getContainer()->get(AccountRepositoryInterface::class);
        self::assertInstanceOf(AccountRepositoryInterface::class, $accounts);

        return $accounts;
    }

    private function guardianship(): Guardianship
    {
        $guardianship = self::getContainer()->get(Guardianship::class);
        self::assertInstanceOf(Guardianship::class, $guardianship);

        return $guardianship;
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
