<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Infrastructure\Persistence\Migrations;

use App\Tests\Support\Database;
use PDO;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

final class Version20261101134009Test extends KernelTestCase
{
    public function testASessionRoundTripsThroughItsTable(): void
    {
        $handler = new PdoSessionHandler(self::ownConnection());
        $handler->open('', 'khmk_session');

        self::assertTrue($handler->write('session-round-trip', 'payload'));
        self::assertSame('payload', $handler->read('session-round-trip'));
        self::assertTrue($handler->destroy('session-round-trip'));
        self::assertSame('', $handler->read('session-round-trip'));
        $handler->close();
    }

    private static function ownConnection(): PDO
    {
        $params = new Database(self::getContainer())->connection()->getParams();

        return new PDO(
            sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $params['host'] ?? '',
                $params['port'] ?? '',
                $params['dbname'] ?? '',
            ),
            $params['user'] ?? null,
            $params['password'] ?? null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}
