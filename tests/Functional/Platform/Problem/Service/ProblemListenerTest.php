<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Problem\Service;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Logs;
use Monolog\Level;

final class ProblemListenerTest extends ApiTestCase
{
    public function testAnUnexpectedErrorRevealsNothingAndIsLoggedAsCritical(): void
    {
        $this->startClient(['debug' => false]);
        $response = $this->jsonRequest('GET', '/api/test/problems/crash');

        $this->assertProblem(500, 'internal');
        self::assertSame(['status', 'code', 'params'], array_keys($this->responseJson()));
        self::assertStringNotContainsString('Secret', (string) $response->getContent());
        self::assertNotSame([], new Logs(self::getContainer())->atOrAbove(Level::Critical));
    }

    public function testAnUnexpectedErrorShowsItsClassAndMessageInDebug(): void
    {
        $this->jsonRequest('GET', '/api/test/problems/crash');

        $this->assertProblem(500, 'internal');
        $this->assertJsonPath('debug.class', 'RuntimeException');
    }

    public function testTheHeadersOfAnHttpExceptionAreKept(): void
    {
        $response = $this->jsonRequest('GET', '/api/test/problems/unavailable');

        $this->assertProblem(503, 'unavailable');
        self::assertSame('30', $response->headers->get('Retry-After'));
    }

    public function testPathsOutsideTheApiKeepSymfonysRendering(): void
    {
        $response = $this->jsonRequest('GET', '/does-not-exist');

        self::assertSame(404, $response->getStatusCode());
        self::assertNotSame('application/problem+json', $response->headers->get('Content-Type'));
    }
}
