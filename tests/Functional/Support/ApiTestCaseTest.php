<?php

declare(strict_types=1);

namespace App\Tests\Functional\Support;

use App\Tests\Support\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

final class ApiTestCaseTest extends ApiTestCase
{
    public function testAnUnknownApiRouteIsNotFound(): void
    {
        $response = $this->jsonRequest('POST', '/api/does-not-exist');

        self::assertSame(404, $response->getStatusCode());
    }

    public function testAnUnsafeRequestWithoutBodySendsAnEmptyJsonObject(): void
    {
        $this->jsonRequest('DELETE', '/api/does-not-exist');

        $browser = self::getClient();
        self::assertInstanceOf(KernelBrowser::class, $browser);
        $request = $browser->getRequest();
        self::assertInstanceOf(Request::class, $request);
        self::assertSame('{}', $request->getContent());
        self::assertSame('application/json', $request->headers->get('Content-Type'));
    }
}
