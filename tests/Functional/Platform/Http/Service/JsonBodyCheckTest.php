<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Http\Service;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class JsonBodyCheckTest extends ApiTestCase
{
    /**
     * @param array<string, string> $server
     */
    #[DataProvider('bodiesThatAreNotJson')]
    public function testAWriteWithoutAJsonBodyIsUnsupported(string $method, array $server, ?string $content): void
    {
        $this->browser()->request($method, '/api/auth/sign-out', server: $server, content: $content);

        $this->assertProblem(415, 'invalidRequest');
    }

    /**
     * @return iterable<string, array{string, array<string, string>, ?string}>
     */
    public static function bodiesThatAreNotJson(): iterable
    {
        yield 'no body' => ['POST', ['CONTENT_TYPE' => 'application/json'], null];
        yield 'no content type' => ['POST', [], '{}'];
        yield 'plain text' => ['POST', ['CONTENT_TYPE' => 'text/plain'], '{}'];
        yield 'form' => ['POST', ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], 'a=1'];
        yield 'multipart' => ['POST', ['CONTENT_TYPE' => 'multipart/form-data; boundary=x'], '--x--'];
        yield 'delete without body' => ['DELETE', [], null];
    }

    public function testAnEmptyJsonObjectPassesWithOrWithoutACharset(): void
    {
        foreach (['application/json', 'application/json; charset=utf-8'] as $type) {
            $this->browser()->request('POST', '/api/auth/sign-out', server: ['CONTENT_TYPE' => $type], content: '{}');

            self::assertResponseStatusCodeSame(204);
        }
    }
}
