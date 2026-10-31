<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Organization\Infrastructure\Persistence\Repository;

use App\Context\Organization\Infrastructure\Persistence\Repository\DoctrineServiceRepository;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineServiceRepositoryTest extends KernelTestCase
{
    public function testSavingANewServiceGivesItTheToneFewestServicesUseArchivedOnesIncluded(): void
    {
        $repository = new DoctrineServiceRepository(new Database(self::getContainer())->entityManager());
        $choir = Structure::service(name: 'Choir');
        $choir->archive(Structure::now());
        $repository->save($choir);
        $sundaySchool = Structure::service(name: 'Sunday School');

        $repository->save($sundaySchool);

        self::assertSame(1, $choir->tone()->value);
        self::assertSame(2, $sundaySchool->tone()->value);
        self::assertEqualsCanonicalizing([1, 2], $repository->tonesInUse()->toArray());
    }
}
