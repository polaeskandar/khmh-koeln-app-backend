<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Logging\Service;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Logs;
use Monolog\Level;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\Cookie;

final class RedactingProcessorTest extends ApiTestCase
{
    public function testACrashLogsNoQueryValuesOrSessionId(): void
    {
        $this->browser()->getCookieJar()->set(new Cookie('khmk_session', 'SESSION-ID-123'));

        $this->jsonRequest('GET', '/api/test/problems/crash?q=Kirollos&token=abc');

        self::assertResponseStatusCodeSame(500);
        $logs = new Logs(self::getContainer());
        self::assertSame([], $logs->containing('Kirollos'));
        self::assertSame([], $logs->containing('token=abc'));
        self::assertSame([], $logs->containing('SESSION-ID-123'));
    }

    #[DataProvider('expectedRefusals')]
    public function testExpectedRefusalsLogNothingAtErrorLevelAndNoParams(string $path): void
    {
        $this->jsonRequest('GET', '/api/test/problems/' . $path);

        $logs = new Logs(self::getContainer());
        self::assertSame([], $logs->atOrAbove(Level::Error));
        self::assertSame([], $logs->containing('Mina'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function expectedRefusals(): iterable
    {
        yield '409 naming a child' => ['rule'];
        yield '403' => ['forbidden'];
        yield '422' => ['invalid-input'];
    }
}
