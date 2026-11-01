<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Http\Service;

use App\Tests\Support\ApiTestCase;
use Override;
use Symfony\Component\HttpFoundation\Response;

final class CorsOriginsTest extends ApiTestCase
{
    private const string VARIABLE = 'CORS_ALLOW_ORIGIN';

    #[Override]
    protected function tearDown(): void
    {
        $_ENV[self::VARIABLE] = '';
        $_SERVER[self::VARIABLE] = '';
        parent::tearDown();
    }

    public function testADevelopmentOriginGetsCredentialedCors(): void
    {
        $_ENV[self::VARIABLE] = 'http://localhost:3000';
        $_SERVER[self::VARIABLE] = 'http://localhost:3000';

        $response = $this->preflight('http://localhost:3000');

        self::assertSame('http://localhost:3000', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    public function testWithoutDevelopmentOriginsNoOriginGetsCors(): void
    {
        $response = $this->preflight('http://localhost:3000');

        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    private function preflight(string $origin): Response
    {
        $this->browser()->request('OPTIONS', '/api/auth/sign-in', server: [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ]);

        return $this->browser()->getResponse();
    }
}
