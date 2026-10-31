<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Infrastructure\Persistence\Repository\DoctrineUserRepository;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class DoctrineUserRepositoryTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testAnAccountIsFoundByItsEmailWhateverTheSpacingAndCase(): void
    {
        self::mockTime('2026-11-08 12:00:00+01:00');
        $repository = new DoctrineUserRepository(new Database(self::getContainer())->entityManager());
        $sara = Accounts::approved('sara.hanna@example.org');

        $repository->save($sara);

        self::assertSame($sara, $repository->findByEmail('  Sara.Hanna@Example.org '));
        self::assertSame('2026-11-08T12:00:00+01:00', $sara->createdAt()->format(DATE_RFC3339));
        self::assertNull($repository->findByEmail('mina@example.org'));
    }
}
