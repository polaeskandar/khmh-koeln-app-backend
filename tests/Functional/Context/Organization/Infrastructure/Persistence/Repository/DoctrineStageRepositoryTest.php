<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Organization\Infrastructure\Persistence\Repository;

use App\Context\Organization\Infrastructure\Persistence\Repository\DoctrineStageRepository;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DoctrineStageRepositoryTest extends KernelTestCase
{
    public function testAStageNamesOnlyItsOwnService(): void
    {
        $database = new Database(self::getContainer());
        $repository = new DoctrineStageRepository($database->entityManager());
        $sundaySchool = Structure::service(name: 'Sunday School');
        $youth = Structure::service(name: 'Youth Meeting');
        $database->persisted($sundaySchool, $youth);
        $beginners = Structure::stage($sundaySchool, name: 'Beginners');

        $repository->save($beginners);

        self::assertTrue($repository->serviceIdOf($beginners->id())?->equals($sundaySchool->id()));
        self::assertFalse($repository->serviceIdOf($beginners->id())?->equals($youth->id()));
        self::assertNull($repository->serviceIdOf(Uuid::v7()));
    }
}
