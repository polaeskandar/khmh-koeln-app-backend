<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Organization\Application\Service;

use App\Context\Organization\Application\Service\ChurchServices;
use App\Context\Organization\Domain\Entity\Service;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class ChurchServicesTest extends KernelTestCase
{
    public function testANameARacingServiceTookIsReportedWithThatServicesState(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $entityManager->getEventManager()->addEventListener(Events::preFlush, new class {
            public function preFlush(PreFlushEventArgs $event): void
            {
                $scheduled = $event->getObjectManager()->getUnitOfWork()->getScheduledEntityInsertions();

                if (!array_any($scheduled, static fn (object $entity): bool => $entity instanceof Service)) {
                    return;
                }

                $event
                    ->getObjectManager()
                    ->getConnection()
                    ->insert('organization.service', [
                        'id' => Uuid::v7()->toRfc4122(),
                        'name' => 'Choir',
                        'name_key' => 'choir',
                        'tone' => 1,
                        'archived_at' => '2026-11-08 12:00:00+01',
                        'created_at' => '2026-11-01 12:00:00+01',
                        'updated_at' => '2026-11-01 12:00:00+01',
                    ]);
            }
        });

        $services = self::getContainer()->get(ChurchServices::class);
        self::assertInstanceOf(ChurchServices::class, $services);

        self::assertSame(
            ['name' => ['code' => 'services.nameTakenArchived', 'params' => ['name' => 'Choir']]],
            ThrownFieldErrors::of(static fn (): Service => $services->create('choir', description: null)),
        );
    }
}
