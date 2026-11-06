<?php

declare(strict_types=1);

namespace App\Tests\Support\Platform\OpenApi;

use Opis\JsonSchema\Errors\ErrorFormatter;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

final class ResponseContract
{
    private static ?OpenApiDocument $document = null;

    public static function assertKept(RouterInterface $router, Request $request, Response $response): void
    {
        $path = $router->getRouteCollection()->get($request->attributes->getString('_route'))?->getPath() ?? '';

        if ($response->isServerError() || !str_starts_with($path, '/api/') || str_starts_with($path, '/api/test')) {
            return;
        }

        $documented = self::document()->response($path, $request->getMethod(), $response->getStatusCode());
        $what = \sprintf('%s %s %d', $request->getMethod(), $path, $response->getStatusCode());
        $content = (string) $response->getContent();

        if ($content === '') {
            Assert::assertSame([], $documented->mediaTypes, $what . ' documents a body but sent none.');

            return;
        }

        $media = explode(';', (string) $response->headers->get('Content-Type'), limit: 2)[0];
        Assert::assertContains($media, $documented->mediaTypes, $what . ' does not document ' . $media . '.');
        $error = self::document()->violation($content, $documented->schemaOf($media));

        Assert::assertNull(
            $error,
            $error === null
                ? ''
                : \sprintf(
                    "%s breaks docs/openapi.json:\n%s\n%s",
                    $what,
                    json_encode(new ErrorFormatter()->format($error), JSON_PRETTY_PRINT),
                    $content,
                ),
        );
    }

    private static function document(): OpenApiDocument
    {
        return self::$document ??= OpenApiDocument::load(\dirname(__DIR__, levels: 4) . '/docs/openapi.json');
    }
}
