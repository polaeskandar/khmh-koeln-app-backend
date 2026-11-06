<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Tests\Support\JsonBody;
use Nelmio\ApiDocBundle\ApiDocGenerator;
use OpenApi\Annotations\OpenApi;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

final class OpenApiDocumentTest extends KernelTestCase
{
    private const string DOCUMENT = '/docs/openapi.json';

    private const array WRITES = ['POST', 'PUT', 'PATCH'];

    public function testTheCommittedDocumentIsUpToDateAndValid(): void
    {
        $generated = $this->generated();

        self::assertTrue($generated->validate(), 'The generated document is not valid OpenAPI.');
        self::assertSame('3.1.0', $generated->openapi);

        self::assertEquals(
            json_decode($generated->toJson(), associative: true, flags: JSON_THROW_ON_ERROR),
            self::committed(),
            'docs/openapi.json is stale: run composer api:doc.',
        );
    }

    public function testEveryApiRouteIsDocumentedWithItsSuccessResponse(): void
    {
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);
        $paths = JsonBody::asArray(self::committed()['paths'] ?? null, 'paths');

        foreach ($router->getRouteCollection() as $name => $route) {
            $path = $route->getPath();

            if (!str_starts_with($path, '/api') || str_starts_with($path, '/api/test')) {
                continue;
            }

            foreach ($route->getMethods() === [] ? ['GET'] : $route->getMethods() as $method) {
                $documented = JsonBody::asArray($paths[$path] ?? [], $path);

                $operation = JsonBody::asArray(
                    $documented[strtolower($method)] ?? null,
                    $method . ' ' . $path . ' (' . $name . ')',
                );

                self::assertNotEmpty(
                    array_filter(
                        array_keys(JsonBody::asArray($operation['responses'] ?? null, 'responses')),
                        static fn (int|string $status): bool => (string) $status !== 'default' && $status < 300,
                    ),
                    sprintf('%s %s has no success response.', $method, $path),
                );

                self::assertTrue(
                    !in_array($method, self::WRITES, strict: true) || array_key_exists('requestBody', $operation),
                    sprintf('%s %s has no request body.', $method, $path),
                );
            }
        }
    }

    private function generated(): OpenApi
    {
        $generator = self::getContainer()->get('nelmio_api_doc.generator.default');
        self::assertInstanceOf(ApiDocGenerator::class, $generator);

        return $generator->generate();
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function committed(): array
    {
        return JsonBody::asArray(
            json_decode(
                (string) file_get_contents(dirname(__DIR__, levels: 2) . self::DOCUMENT),
                associative: true,
                flags: JSON_THROW_ON_ERROR,
            ),
            'openapi.json',
        );
    }
}
