<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Learning\Application\Service;

use App\Context\Learning\Application\Service\HomeworkLifecycle;
use App\Context\Learning\Application\Service\HomeworkWrites;
use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\HomeworkStatus;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Tests\Support\CommittedRows;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

#[SkipDatabaseRollback]
final class HomeworkLifecycleTest extends KernelTestCase
{
    private ?Connection $other = null;

    private ?Service $service = null;

    private ?Stage $stage = null;

    private ?Lesson $lesson = null;

    private ?HomeworkAssignment $task = null;

    #[Override]
    protected function setUp(): void
    {
        $this->service = Structure::service('Locked School');
        $this->stage = Structure::stage($this->service, '1st Class', 0);
        new Database(self::getContainer())->persisted($this->service, $this->stage);
        $this->lesson = new Lessons(self::getContainer())->stored($this->stage, null, '2026-11-15');

        $this->task = new HomeworkAssignment(
            $this->lesson,
            HomeworkText::of('Learn the verse', 'Read it twice.', null),
            Materials::none(),
            createdBy: null,
        );

        $this->task->changeState(
            $this->task
                ->state()
                ->withStatus(HomeworkStatus::Published)
                ->withPublishedAt(new DateTimeImmutable('2026-11-01T09:00:00+01:00')),
        );

        new Database(self::getContainer())->persisted($this->task);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->other?->close();

        new CommittedRows(new Database(self::getContainer())->connection())->delete([
            'learning.homework_assignment' => [$this->task?->id()->toRfc4122() ?? ''],
            'learning.lesson' => [$this->lesson?->id()->toRfc4122() ?? ''],
            'organization.stage' => [$this->stage?->id()->toRfc4122() ?? ''],
            'organization.service' => [$this->service?->id()->toRfc4122() ?? ''],
        ]);

        parent::tearDown();
    }

    public function testAnUnpublishWaitsForTheTaskRowAndChangesNothingOnTimeout(): void
    {
        $taskId = $this->task?->id()->toRfc4122() ?? '';
        $other = DriverManager::getConnection(new Database(self::getContainer())->connection()->getParams());
        $this->other = $other;
        $other->beginTransaction();
        $other->fetchOne('SELECT id FROM learning.homework_assignment WHERE id = ? FOR UPDATE', [$taskId]);
        $connection = new Database(self::getContainer())->connection();
        $connection->executeStatement("SET lock_timeout = '200ms'");
        $sqlState = null;

        try {
            $this->service(HomeworkWrites::class)->whileTaskLocked(
                Uuid::fromString($taskId),
                fn (HomeworkAssignment $task): HomeworkAssignment => $this->service(
                    HomeworkLifecycle::class,
                )->unpublish($task),
            );
        } catch (DriverException $refused) {
            $sqlState = $refused->getSQLState();
        } finally {
            $other->rollBack();
            $connection->executeStatement('SET lock_timeout = 0');
        }

        self::assertSame('55P03', $sqlState);

        self::assertSame('published', $connection->fetchOne('SELECT status FROM learning.homework_assignment WHERE id = ?', [
            $taskId,
        ]));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $class): object
    {
        $service = self::getContainer()->get($class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
