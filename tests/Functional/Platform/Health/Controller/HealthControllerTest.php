<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Health\Controller;

use App\Platform\Health\Contract\DatabaseProbeInterface;
use App\Tests\Support\ApiTestCase;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use Override;

final class HealthControllerTest extends ApiTestCase
{
    #[SkipDatabaseRollback]
    public function testLivenessAnswersWithoutTouchingTheDatabase(): void
    {
        $response = $this->jsonRequest('GET', '/api/health');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['status' => 'ok'], $this->responseJson());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));

        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        self::assertFalse($connection->isConnected());
    }

    public function testReadinessReportsTheDatabase(): void
    {
        $response = $this->jsonRequest('GET', '/api/health/ready');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['status' => 'ok', 'checks' => ['database' => 'ok']], $this->responseJson());
    }

    public function testReadinessFailsWhenTheDatabaseIsUnavailable(): void
    {
        $this->browser();

        self::getContainer()->set(DatabaseProbeInterface::class, new class implements DatabaseProbeInterface {
            #[Override]
            public function isAvailable(int $timeoutSeconds): bool
            {
                return false;
            }
        });

        $response = $this->jsonRequest('GET', '/api/health/ready');

        self::assertSame(503, $response->getStatusCode());
        self::assertSame(['status' => 'unavailable', 'checks' => ['database' => 'unavailable']], $this->responseJson());
    }
}
