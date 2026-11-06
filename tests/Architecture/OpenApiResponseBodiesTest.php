<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use Nelmio\ApiDocBundle\ApiDocGenerator;
use OpenApi\Annotations as OA;
use OpenApi\Undefined;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OpenApiResponseBodiesTest extends KernelTestCase
{
    private const array SUCCESSES_WITH_BODY = ['200', '201', '202'];

    public function testEverySuccessWithABodyDescribesIt(): void
    {
        $generator = self::getContainer()->get('nelmio_api_doc.generator.default');
        self::assertInstanceOf(ApiDocGenerator::class, $generator);
        $operations = array_merge(...array_map(self::operationsOf(...), $generator->generate()->paths));

        self::assertSame([], array_merge(...array_map(self::undescribedOf(...), $operations)));
    }

    /**
     * @return list<OA\Operation>
     */
    private static function operationsOf(OA\PathItem $path): array
    {
        return array_values(array_filter(
            [$path->get, $path->put, $path->post, $path->patch, $path->delete],
            static fn (mixed $operation): bool => $operation instanceof OA\Operation,
        ));
    }

    /**
     * @return list<string>
     */
    private static function undescribedOf(OA\Operation $operation): array
    {
        return array_values(array_map(
            static fn (OA\Response $response): string => \sprintf(
                '%s %s %s',
                strtoupper($operation->method),
                $operation->path,
                $response->response,
            ),
            array_filter(
                $operation->responses,
                static fn (OA\Response $response): bool => (
                    \in_array((string) $response->response, self::SUCCESSES_WITH_BODY, strict: true)
                    && Undefined::isDefault($response->content)
                ),
            ),
        ));
    }
}
