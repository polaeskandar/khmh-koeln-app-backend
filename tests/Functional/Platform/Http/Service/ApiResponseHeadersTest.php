<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Http\Service;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ApiResponseHeadersTest extends ApiTestCase
{
    #[DataProvider('responses')]
    public function testEveryApiResponseForbidsCachingAndSniffing(string $path, int $status): void
    {
        $response = $this->jsonRequest('GET', $path);

        self::assertSame($status, $response->getStatusCode());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function responses(): iterable
    {
        yield '200' => ['/api/health', 200];
        yield '403' => ['/api/test/problems/forbidden', 403];
        yield '404' => ['/api/does-not-exist', 404];
        yield '500' => ['/api/test/problems/crash', 500];
    }
}
