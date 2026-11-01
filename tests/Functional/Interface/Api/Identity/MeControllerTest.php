<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class MeControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testTheAccountComesWithItsRolesPermissionsAndExactlyTheDocumentedKeys(): void
    {
        $id = $this->signedInWith('daniel@example.org', 'servant', 'parent');

        $this->jsonRequest('GET', '/api/me');

        self::assertResponseIsSuccessful();
        $body = $this->responseJson();
        self::assertSame(['account', 'roles', 'permissions', 'today'], array_keys($body));

        self::assertSame(
            ['id' => $id, 'email' => 'daniel@example.org', 'displayName' => 'Sara Hanna', 'language' => 'de'],
            $body['account'] ?? null,
        );

        $this->assertJsonPath('roles.0.name', 'Parent');
        $this->assertJsonPath('roles.1.name', 'Servant');

        $permissions = JsonBody::asArray($body['permissions'] ?? null, 'permissions');
        self::assertContains(['action' => 'read', 'resource' => 'services'], $permissions);
        self::assertContains(['action' => 'read', 'resource' => 'parentPortal'], $permissions);
        self::assertNotContains(['action' => 'update', 'resource' => 'services'], $permissions);
    }

    public function testAnAccountWithoutRolesHasEmptyLists(): void
    {
        $this->signedInWith('someone@example.org');

        $this->jsonRequest('GET', '/api/me');

        $this->assertJsonPath('roles', []);
        $this->assertJsonPath('permissions', []);
    }

    public function testTodayIsTheChurchDate(): void
    {
        self::mockTime('2026-11-07T23:30:00Z');
        $this->signedInWith('someone@example.org');

        $this->jsonRequest('GET', '/api/me');

        $this->assertJsonPath('today', '2026-11-08');
    }

    public function testWithoutASessionTheAccountIsUnauthenticated(): void
    {
        $this->jsonRequest('GET', '/api/me');

        $this->assertProblem(401, 'auth.unauthenticated');
    }

    public function testDisablingTheAccountEndsTheSession(): void
    {
        $id = $this->signedInWith('someone@example.org', 'servant');

        new Database(self::getContainer())
            ->connection()
            ->update('identity.app_user', ['disabled_at' => '2026-11-09 12:00:00+01'], ['id' => $id]);

        $this->jsonRequest('GET', '/api/me');

        $this->assertProblem(401, 'auth.unauthenticated');
    }

    private function signedInWith(string $email, string ...$roles): string
    {
        $id = new SignInAccounts(self::getContainer())->add($email);
        new RoleGrants(self::getContainer())->grant($id, ...$roles);
        $this->signInAs($email, SignInAccounts::TYPED);

        return $id;
    }
}
