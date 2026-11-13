<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Access\Infrastructure\Security;

use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Access\Domain\ValueObject\Access;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Infrastructure\Security\SecurityUser;
use App\Tests\Support\Context\Access\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Uid\Uuid;

final class AccessVoterTest extends KernelTestCase
{
    private const array ALL = ['create', 'read', 'update', 'delete'];

    private const array GRANTS = [
        'administrator' => [
            'users' => self::ALL,
            'roles' => self::ALL,
            'services' => self::ALL,
            'stages' => self::ALL,
            'sessions' => self::ALL,
            'attendance' => self::ALL,
            'members' => self::ALL,
            'lessons' => self::ALL,
            'homework' => self::ALL,
        ],
        'service_leader' => [
            'services' => ['read', 'update'],
            'stages' => self::ALL,
            'sessions' => self::ALL,
            'attendance' => self::ALL,
            'members' => self::ALL,
            'lessons' => self::ALL,
            'homework' => self::ALL,
        ],
        'servant' => [
            'services' => ['read'],
            'attendance' => self::ALL,
            'members' => self::ALL,
            'lessons' => self::ALL,
            'homework' => self::ALL,
        ],
        'parent' => [
            'parentPortal' => ['read'],
        ],
    ];

    #[DataProvider('seededRoles')]
    public function testEachSeededRoleGetsExactlyItsPermissions(string $role): void
    {
        $userId = $this->account(['email' => $role . '@example.org'], $role);

        foreach (Resource::cases() as $resource) {
            foreach (Action::cases() as $action) {
                $expected = in_array($action->value, self::GRANTS[$role][$resource->value] ?? [], strict: true);

                self::assertSame(
                    $expected,
                    $this->allows($userId, $action, $resource),
                    $action->value . ' ' . $resource->value,
                );
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function seededRoles(): iterable
    {
        foreach (array_keys(self::GRANTS) as $role) {
            yield $role => [$role];
        }
    }

    public function testRolesAddUp(): void
    {
        $userId = $this->account([], 'servant', 'parent');

        self::assertTrue($this->allows($userId, Action::Create, Resource::Attendance));
        self::assertTrue($this->allows($userId, Action::Read, Resource::ParentPortal));
        self::assertFalse($this->allows($userId, Action::Create, Resource::Stages));
    }

    public function testAnAccountWithoutRolesGetsNothing(): void
    {
        $userId = $this->account([]);

        self::assertFalse($this->allows($userId, Action::Read, Resource::Services));
    }

    public function testADisabledAdministratorGetsNothing(): void
    {
        $userId = $this->account(['disabled_at' => '2026-11-09 12:00:00+01'], 'administrator');

        self::assertFalse($this->allows($userId, Action::Read, Resource::Services));
    }

    public function testARemovedRoleIsDeniedForTheReloadedAccount(): void
    {
        $userId = $this->account([], 'servant');
        self::assertTrue($this->allows($userId, Action::Create, Resource::Attendance));

        $this->database()->connection()->executeStatement('DELETE FROM access.user_role WHERE user_id = ?', [$userId]);

        $this->database()->entityManager()->clear();

        self::assertFalse($this->allows($userId, Action::Create, Resource::Attendance));
    }

    public function testAnAnonymousTokenGetsNothing(): void
    {
        $decisions = self::getContainer()->get('security.access.decision_manager');
        self::assertInstanceOf(AccessDecisionManagerInterface::class, $decisions);

        self::assertFalse($decisions->decide(
            new NullToken(),
            [Access::ATTRIBUTE],
            new Access(Action::Read, Resource::Services),
        ));
    }

    /**
     * @param array<string, string|null> $columns
     */
    private function account(array $columns, string ...$roles): string
    {
        $id = Uuid::v7()->toRfc4122();

        $this
            ->database()
            ->connection()
            ->insert('identity.app_user', Accounts::row(['id' => $id, ...$columns]));

        new RoleGrants(self::getContainer())->grant($id, ...$roles);

        return $id;
    }

    private function allows(string $userId, Action $action, Resource $resource): bool
    {
        $users = self::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        $user = $users->find(Uuid::fromString($userId));
        self::assertNotNull($user);

        $decisions = self::getContainer()->get('security.access.decision_manager');
        self::assertInstanceOf(AccessDecisionManagerInterface::class, $decisions);

        return $decisions->decide(
            new UsernamePasswordToken(SecurityUser::of($user), 'api', ['ROLE_USER']),
            [Access::ATTRIBUTE],
            new Access($action, $resource),
        );
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
