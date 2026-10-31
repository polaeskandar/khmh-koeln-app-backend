<?php

declare(strict_types=1);

namespace App\Tests;

use stdClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiTestCase extends WebTestCase
{
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    private ?KernelBrowser $browser = null;

    /**
     * @param array<array-key, mixed> $body
     * @param array<string, string> $headers
     */
    protected function jsonRequest(string $method, string $uri, array $body = [], array $headers = []): Response
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace(search: '-', replace: '_', subject: $name))] = $value;
        }

        $content = null;
        if (!in_array(strtoupper($method), self::SAFE_METHODS, strict: true)) {
            $server['CONTENT_TYPE'] = 'application/json';
            $content = json_encode($body === [] ? new stdClass() : $body, JSON_THROW_ON_ERROR);
        }

        $browser = $this->browser();
        $browser->request($method, $uri, server: $server, content: $content);

        return $browser->getResponse();
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function responseJson(): array
    {
        $content = (string) $this->browser()->getResponse()->getContent();

        return self::asArray(json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR), 'The response body');
    }

    protected function assertJsonPath(string $path, mixed $expected): void
    {
        self::assertSame($expected, self::valueAt($this->responseJson(), explode('.', $path), $path));
    }

    /**
     * @param list<string> $keys
     */
    private static function valueAt(mixed $node, array $keys, string $path): mixed
    {
        if ($keys === []) {
            return $node;
        }

        $object = self::asArray($node, sprintf('"%s"', $path));
        if (!array_key_exists($keys[0], $object)) {
            self::fail(sprintf('The response has no "%s".', $path));
        }

        return self::valueAt($object[$keys[0]], array_slice($keys, offset: 1), $path);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function asArray(mixed $value, string $what): array
    {
        self::assertIsArray($value, sprintf('%s is not a JSON object or array.', $what));

        return $value;
    }

    private function browser(): KernelBrowser
    {
        return $this->browser ??= self::createClient();
    }
}
