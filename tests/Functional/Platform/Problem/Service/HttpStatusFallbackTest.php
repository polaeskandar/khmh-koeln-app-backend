<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Problem\Service;

use App\Tests\Support\ApiTestCase;

final class HttpStatusFallbackTest extends ApiTestCase
{
    public function testMalformedJsonIsAnInvalidRequest(): void
    {
        $this->browser()->request(
            'POST',
            '/api/test/problems/payload',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"name":',
        );

        $this->assertProblem(400, 'invalidRequest');
    }

    public function testANonJsonBodyIsAnInvalidRequest(): void
    {
        $this->browser()->request(
            'POST',
            '/api/test/problems/payload',
            server: ['CONTENT_TYPE' => 'text/plain'],
            content: 'name',
        );

        $this->assertProblem(415, 'invalidRequest');
    }

    public function testAWrongMethodIsAnInvalidRequest(): void
    {
        $response = $this->jsonRequest('GET', '/api/test/problems/payload');

        $this->assertProblem(405, 'invalidRequest');
        self::assertSame('POST', $response->headers->get('Allow'));
    }

    public function testAnUnauthorizedRequestIsUnauthenticated(): void
    {
        $this->jsonRequest('GET', '/api/test/problems/unauthorized');

        $this->assertProblem(401, 'auth.unauthenticated');
    }
}
