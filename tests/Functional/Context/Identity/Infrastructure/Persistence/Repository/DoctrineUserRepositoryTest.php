<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Infrastructure\Persistence\Repository\DoctrineUserRepository;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Database;
use App\Tests\Support\Logs;
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

    public function testTheWriteLockLoadsTheAccountForUpdateInsideATransaction(): void
    {
        $database = new Database(self::getContainer());
        $repository = new DoctrineUserRepository($database->entityManager());
        $sara = Accounts::approved('sara@example.org');
        $repository->save($sara);
        $database->entityManager()->clear();
        $locked = null;

        $repository->withWriteLock($sara->id(), static function (User $user) use ($database, &$locked): void {
            self::assertTrue($database->connection()->isTransactionActive());
            self::assertNotSame([], new Logs(self::getContainer())->containing('FOR UPDATE'));
            $locked = $user->id()->toRfc4122();
        });

        self::assertSame($sara->id()->toRfc4122(), $locked);
    }

    public function testTheWriteLockReadsAnAlreadyLoadedAccountAfresh(): void
    {
        $database = new Database(self::getContainer());
        $repository = new DoctrineUserRepository($database->entityManager());
        $sara = Accounts::approved('sara@example.org');
        $repository->save($sara);

        $database->connection()->update(
            'identity.app_user',
            ['password_hash' => 'changed meanwhile'],
            ['id' => $sara->id()->toRfc4122()],
        );

        $seen = null;

        $repository->withWriteLock($sara->id(), static function (User $locked) use (&$seen): void {
            $seen = $locked->passwordHash();
        });

        self::assertSame('changed meanwhile', $seen);
    }
}
