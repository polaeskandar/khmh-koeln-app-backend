<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Application\Service;

use App\Tests\Support\ApiTestCase;

final class AccessCheckTest extends ApiTestCase
{
    public function testEveryRequiredAccessMustBeGranted(): void
    {
        $this->signInWithRoles('service_leader@example.org', 'service_leader');

        $this->jsonRequest('GET', '/api/testing/access');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('granted', true);
    }

    public function testOneMissingAccessIsForbidden(): void
    {
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('GET', '/api/testing/access');

        $this->assertProblem(403, 'forbidden');
    }

    public function testWithoutASessionTheRouteIsUnauthenticated(): void
    {
        $this->jsonRequest('GET', '/api/testing/access');

        $this->assertProblem(401, 'auth.unauthenticated');
    }
}
