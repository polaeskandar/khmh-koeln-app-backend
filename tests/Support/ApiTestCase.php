<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Override;
use stdClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiTestCase extends WebTestCase
{
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::createClient();
    }

    /**
     * @param array<array-key, mixed> $body
     * @param array<string, string> $headers
     */
    protected function jsonRequest(string $method, string $uri, array $body = [], array $headers = []): Response
    {
        $server = [
            'HTTP_ACCEPT' => 'application/json',
            ...array_combine(array_map(self::serverName(...), array_keys($headers)), $headers),
        ];

        $content = null;
        if (!in_array(strtoupper($method), self::SAFE_METHODS, strict: true)) {
            $server['CONTENT_TYPE'] = 'application/json';
            $content = json_encode($body === [] ? new stdClass() : $body, JSON_THROW_ON_ERROR);
        }

        $this->browser()->request($method, $uri, server: $server, content: $content);

        return $this->browser()->getResponse();
    }

    /**
     * @param array<string, bool|int|string> $options
     */
    protected function startClient(array $options): void
    {
        self::ensureKernelShutdown();
        self::createClient($options);
    }

    protected function browser(): KernelBrowser
    {
        $browser = self::getClient();
        self::assertInstanceOf(KernelBrowser::class, $browser);

        return $browser;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function responseJson(): array
    {
        $content = (string) $this->browser()->getResponse()->getContent();

        return JsonBody::asArray(
            json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR),
            'The response body',
        );
    }

    protected function assertJsonPath(string $path, mixed $expected): void
    {
        self::assertSame($expected, JsonBody::valueAt($this->responseJson(), explode('.', $path), $path));
    }

    /**
     * @param array<string, string|int|float|bool|null> $params
     */
    protected function assertProblem(int $status, string $code, array $params = []): void
    {
        $response = $this->browser()->getResponse();
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $body = $this->responseJson();
        self::assertSame($status, $body['status'] ?? null);
        self::assertSame($code, $body['code'] ?? null);
        self::assertSame(
            JsonBody::sorted($params),
            JsonBody::sorted(JsonBody::asArray($body['params'] ?? null, '"params"')),
        );
    }

    /**
     * @param array<string, string|int|float|bool|null> $params
     */
    protected function assertFieldError(string $field, string $code, array $params = []): void
    {
        $fieldErrors = JsonBody::asArray($this->responseJson()['fieldErrors'] ?? null, '"fieldErrors"');
        $error = JsonBody::asArray($fieldErrors[$field] ?? null, $field);

        self::assertSame($code, $error['code'] ?? null, $field);
        self::assertSame(
            JsonBody::sorted($params),
            JsonBody::sorted(JsonBody::asArray($error['params'] ?? null, $field)),
            $field,
        );
    }

    private static function serverName(string $header): string
    {
        return 'HTTP_' . ($header |> strtoupper(...) |> self::underscored(...));
    }

    private static function underscored(string $name): string
    {
        return str_replace(search: '-', replace: '_', subject: $name);
    }
}
