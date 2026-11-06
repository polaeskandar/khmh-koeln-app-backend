<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Organization\Infrastructure\Persistence\Repository;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\ValueObject\Tone;
use App\Context\Organization\Infrastructure\Persistence\Repository\DoctrineServiceRepository;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineServiceRepositoryTest extends KernelTestCase
{
    public function testTonesInUseCountStoredArchivedAndPendingServices(): void
    {
        $database = new Database(self::getContainer());
        $repository = new DoctrineServiceRepository($database->entityManager());
        $choir = Structure::service(name: 'Choir');
        $choir->archive(Structure::now());
        $repository->save($choir);
        $sundaySchool = Structure::service(name: 'Sunday School');

        $database
            ->entityManager()
            ->persist(
                new Service(
                    $sundaySchool->name(),
                    $sundaySchool->description(),
                    Tone::leastUsed(new ArrayCollection([1])),
                ),
            );

        self::assertEqualsCanonicalizing([1, 2], $repository->tonesInUse()->toArray());
    }
}
