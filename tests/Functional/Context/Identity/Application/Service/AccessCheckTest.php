<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Application\Service;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;

final class AccessCheckTest extends ApiTestCase
{
    public function testEveryRequiredAccessMustBeGranted(): void
    {
        $this->signedInWith('service_leader');

        $this->jsonRequest('GET', '/api/testing/access');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('granted', true);
    }

    public function testOneMissingAccessIsForbidden(): void
    {
        $this->signedInWith('servant');

        $this->jsonRequest('GET', '/api/testing/access');

        $this->assertProblem(403, 'forbidden');
    }

    public function testWithoutASessionTheRouteIsUnauthenticated(): void
    {
        $this->jsonRequest('GET', '/api/testing/access');

        $this->assertProblem(401, 'auth.unauthenticated');
    }

    private function signedInWith(string $role): void
    {
        $id = new SignInAccounts(self::getContainer())->add($role . '@example.org');
        new RoleGrants(self::getContainer())->grant($id, $role);
        $this->signInAs($role . '@example.org', SignInAccounts::TYPED);
    }
}
