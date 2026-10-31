<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Organization\Infrastructure\Persistence\Listener;

use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ToneListenerTest extends KernelTestCase
{
    public function testEveryNewServiceTakesTheNextFreeToneWhenItIsSaved(): void
    {
        $database = new Database(self::getContainer());

        $services = [
            Structure::service(name: 'Sunday School'),
            Structure::service(name: 'Youth'),
            Structure::service(name: 'Choir'),
        ];

        foreach ($services as $service) {
            $database->persisted($service);
        }

        self::assertSame([1, 2, 3], array_map(static fn ($service): int => $service->tone()->value, $services));
    }

    public function testServicesPersistedBeforeOneFlushTakeSuccessiveTones(): void
    {
        $database = new Database(self::getContainer());
        $sundaySchool = Structure::service(name: 'Sunday School');
        $youth = Structure::service(name: 'Youth');
        $choir = Structure::service(name: 'Choir');

        $database->persisted($sundaySchool, $youth, $choir);

        self::assertSame(1, $sundaySchool->tone()->value);
        self::assertSame(2, $youth->tone()->value);
        self::assertSame(3, $choir->tone()->value);
    }

    public function testPendingAndStoredServicesBothCount(): void
    {
        $database = new Database(self::getContainer());
        $database->persisted(Structure::service(name: 'Sunday School'));
        $entityManager = $database->entityManager();
        $youth = Structure::service(name: 'Youth');
        $choir = Structure::service(name: 'Choir');

        $entityManager->persist($youth);
        $entityManager->persist($choir);

        self::assertSame(2, $youth->tone()->value);
        self::assertSame(3, $choir->tone()->value);
    }
}
