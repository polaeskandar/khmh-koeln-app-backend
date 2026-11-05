<?php

declare(strict_types=1);

namespace App\Tests\Unit\Platform\Errors\Service;

use App\Platform\Errors\Service\SentryErrorTracker;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\ClientBuilder;
use Sentry\Options;
use Sentry\State\HubInterface;

final class SentryErrorTrackerTest extends TestCase
{
    public function testWithoutADsnNoEventIsBuilt(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->method('getClient')->willReturn(new ClientBuilder(new Options(['dsn' => null]))->getClient());
        $hub->expects(self::never())->method('captureException');

        new SentryErrorTracker($hub)->capture(new RuntimeException('Crash'));
    }
}
