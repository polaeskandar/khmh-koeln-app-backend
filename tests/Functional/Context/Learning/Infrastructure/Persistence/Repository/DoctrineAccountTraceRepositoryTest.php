<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Learning\Infrastructure\Persistence\Repository;

use App\Context\Learning\Infrastructure\Persistence\Repository\DoctrineAccountTraceRepository;
use App\Library\Types\Model\Integers;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DoctrineAccountTraceRepositoryTest extends KernelTestCase
{
    public function testForgettingAnAccountRemovesItsHelperRowsAndKeepsTheLesson(): void
    {
        $lesson = new Lessons(self::getContainer())->inThirdClass();
        $christine = new SignInAccounts(self::getContainer())->add('christine@example.org');
        $daniel = new SignInAccounts(self::getContainer())->add('daniel@example.org');
        $connection = new Database(self::getContainer())->connection();

        foreach ([$christine, $daniel] as $helper) {
            $connection->insert('learning.lesson_collaborator', [
                'lesson_id' => $lesson->id()->toRfc4122(),
                'user_id' => $helper,
            ]);
        }

        $traces = self::getContainer()->get(DoctrineAccountTraceRepository::class);
        self::assertInstanceOf(DoctrineAccountTraceRepository::class, $traces);
        $traces->forget(Uuid::fromString($christine));

        self::assertSame(
            [$daniel],
            $connection->fetchFirstColumn('SELECT user_id::text FROM learning.lesson_collaborator'),
        );

        self::assertSame(1, Integers::of($connection->fetchOne('SELECT count(*) FROM learning.lesson')));
    }
}
