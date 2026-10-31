<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Http\Service;

use App\Tests\Support\ApiTestCase;

final class ApiResponseHeadersTest extends ApiTestCase
{
    public function testEveryApiResponseForbidsCachingAndSniffing(): void
    {
        $response = $this->jsonRequest('GET', '/api/does-not-exist');

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }
}
