<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Errors\Service;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Platform\Errors\RecordingTransport;
use LogicException;
use RuntimeException;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\Options;
use Sentry\Serializer\PayloadSerializer;
use Symfony\Component\BrowserKit\Cookie;

final class SentryErrorTrackerTest extends ApiTestCase
{
    public function testAServerErrorSendsOneEventWithoutRequestData(): void
    {
        $this->browser()->disableReboot();

        $this->browser()->getCookieJar()->set(new Cookie('khmk_session', 'secret-cookie'));

        $this->jsonRequest('GET', '/api/test/problems/crash?q=Kirollos', headers: [
            'X-Api-Key' => 'secret-token',
        ]);

        self::assertResponseStatusCodeSame(500);
        $events = $this->transport()->events();
        self::assertCount(1, $events);
        $event = $events[0] ?? self::fail('No event.');
        $payload = new PayloadSerializer(new Options())->serialize($event);

        foreach (['Kirollos', 'secret-cookie', 'secret-token', 'q=', 'Mina Hanna', 'Secret detail'] as $hidden) {
            self::assertStringNotContainsString($hidden, $payload);
        }

        self::assertSame(['method' => 'GET', 'url' => '/api/test/problems/crash'], $event->getRequest());
        self::assertSame([], $event->getBreadcrumbs());
        self::assertSame([[RuntimeException::class, '[redacted]']], self::exceptionsOf($event));
    }

    public function testEveryMessageInAnExceptionChainIsRedacted(): void
    {
        $this->browser()->disableReboot();

        $this->jsonRequest('GET', '/api/test/problems/crash-chain');

        self::assertResponseStatusCodeSame(500);
        $event = $this->transport()->events()[0] ?? self::fail('No event.');
        $payload = new PayloadSerializer(new Options())->serialize($event);

        foreach (['Mina Hanna', 'mina@example.org', 'Key (email)'] as $hidden) {
            self::assertStringNotContainsString($hidden, $payload);
        }

        self::assertSame(
            [
                [RuntimeException::class, '[redacted]'],
                [LogicException::class,   '[redacted]'],
            ],
            self::exceptionsOf($event),
        );
    }

    public function testAClientErrorSendsNothing(): void
    {
        $this->browser()->disableReboot();

        $this->jsonRequest('GET', '/api/test/problems/rule?q=Kirollos');

        self::assertResponseStatusCodeSame(409);
        self::assertSame([], $this->transport()->events());
    }

    private function transport(): RecordingTransport
    {
        $transport = self::getContainer()->get(RecordingTransport::class);
        self::assertInstanceOf(RecordingTransport::class, $transport);

        return $transport;
    }

    /**
     * @return list<array{string, string}>
     */
    private static function exceptionsOf(Event $event): array
    {
        return array_values(array_map(static fn (ExceptionDataBag $exception): array => [
            $exception->getType(),
            $exception->getValue(),
        ], $event->getExceptions()));
    }
}
