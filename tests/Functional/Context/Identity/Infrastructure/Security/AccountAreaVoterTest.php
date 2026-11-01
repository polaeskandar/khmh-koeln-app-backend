<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Infrastructure\Security;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;

final class AccountAreaVoterTest extends ApiTestCase
{
    private const string STAFF_ROUTE = '/api/testing/access';

    private const string PARENT_ROUTE = '/api/parent/testing/probe';

    /**
     * @param list<string> $roles
     */
    #[DataProvider('accounts')]
    public function testEachAccountReachesOnlyItsAreas(array $roles, int $staffStatus, int $parentStatus): void
    {
        $this->signedInWith(...$roles);

        $this->jsonRequest('GET', self::STAFF_ROUTE);
        self::assertResponseStatusCodeSame($staffStatus);

        $this->jsonRequest('GET', self::PARENT_ROUTE);
        self::assertResponseStatusCodeSame($parentStatus);
    }

    /**
     * @return iterable<string, array{list<string>, int, int}>
     */
    public static function accounts(): iterable
    {
        yield 'administrator' => [['administrator'], 200, 403];
        yield 'servant' => [['servant'], 403, 403];
        yield 'parent' => [['parent'], 403, 200];
        yield 'staff and parent' => [['service_leader', 'parent'], 200, 200];
        yield 'no roles' => [[], 403, 403];
    }

    public function testRoleChangesApplyOnTheNextRequest(): void
    {
        $id = $this->signedInWith('servant');
        $this->jsonRequest('GET', self::PARENT_ROUTE);
        self::assertResponseStatusCodeSame(403);

        new RoleGrants(self::getContainer())->grant($id, 'parent');
        $this->jsonRequest('GET', self::PARENT_ROUTE);
        self::assertResponseStatusCodeSame(200);

        $this
            ->database()
            ->connection()
            ->executeStatement('DELETE FROM identity.user_role WHERE role_id = (SELECT id FROM identity.role WHERE name_key = ?)', [
                'servant',
            ]);

        $this->jsonRequest('GET', '/api/testing/access');
        $this->assertProblem(403, 'forbidden');
    }

    private function signedInWith(string ...$roles): string
    {
        $id = new SignInAccounts(self::getContainer())->add('someone@example.org');
        new RoleGrants(self::getContainer())->grant($id, ...$roles);
        $this->signInAs('someone@example.org', SignInAccounts::TYPED);

        return $id;
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
