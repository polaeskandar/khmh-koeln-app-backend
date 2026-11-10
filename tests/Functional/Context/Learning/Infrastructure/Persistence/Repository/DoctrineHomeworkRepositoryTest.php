<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Learning\Infrastructure\Persistence\Repository;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\HomeworkStatus;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\LessonText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Context\Learning\Infrastructure\Persistence\Repository\DoctrineHomeworkRepository;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\CommittedRows;
use App\Tests\Support\Database;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Override;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

#[SkipDatabaseRollback]
final class DoctrineHomeworkRepositoryTest extends KernelTestCase
{
    private const string NOWAIT = 'SELECT id FROM learning.homework_assignment WHERE id = ? FOR UPDATE NOWAIT';

    private const string PUBLISH = <<<'SQL'
        UPDATE learning.homework_assignment SET status = 'published', published_at = now() WHERE id = ?
        SQL;

    private ?HomeworkAssignment $task = null;

    #[Override]
    protected function setUp(): void
    {
        $lesson = new Lesson(
            Uuid::v7(),
            new MeetingLink(null, LocalDate::of('2026-11-15')),
            new LessonText('Psalm 23'),
            null,
        );

        $task = new HomeworkAssignment(
            $lesson,
            HomeworkText::of('Learn the verse', 'Say it aloud.', null),
            Materials::none(),
            null,
        );

        $this->database()->persisted($lesson, $task);
        $this->database()->entityManager()->clear();
        $this->task = $task;
    }

    #[Override]
    protected function tearDown(): void
    {
        $task = $this->task();

        new CommittedRows($this->database()->connection())->delete([
            'learning.homework_assignment' => [$task->id()->toRfc4122()],
            'learning.lesson' => [$task->lesson()->id()->toRfc4122()],
        ]);

        parent::tearDown();
    }

    public function testWhileTheLockIsHeldAnotherConnectionCannotTakeTheRow(): void
    {
        $other = DriverManager::getConnection($this->database()->connection()->getParams());

        $this->homework()->withLock($this->task()->id(), static function (HomeworkAssignment $task) use (
            $other,
        ): HomeworkAssignment {
            $sqlState = null;

            try {
                $other->beginTransaction();
                $other->fetchOne(self::NOWAIT, [$task->id()->toRfc4122()]);
            } catch (DriverException $refused) {
                $sqlState = $refused->getSQLState();
            } finally {
                $other->rollBack();
            }

            self::assertSame('55P03', $sqlState);

            return $task;
        });

        $this->assertFree();
    }

    public function testAFailureReleasesTheLock(): void
    {
        $failure = null;

        try {
            $this->homework()->withLock(
                $this->task()->id(),
                static fn (HomeworkAssignment $task): HomeworkAssignment => throw new RuntimeException(
                    $task->id()->toRfc4122(),
                ),
            );
        } catch (RuntimeException $caught) {
            $failure = $caught->getMessage();
        }

        self::assertSame($this->task()->id()->toRfc4122(), $failure);
        $this->assertFree();
    }

    public function testALoadedTaskIsLockedWithWhatIsStored(): void
    {
        $id = $this->task()->id();
        $this->database()->connection()->executeStatement(self::PUBLISH, [$id->toRfc4122()]);

        self::assertInstanceOf(HomeworkAssignment::class, $this
            ->database()
            ->entityManager()
            ->find(HomeworkAssignment::class, $id));

        $this->database()->connection()->executeStatement(self::PUBLISH, [$id->toRfc4122()]);

        $locked = $this->homework()->withLock($id, static fn (HomeworkAssignment $task): HomeworkAssignment => $task);

        self::assertNotNull($locked);
        self::assertSame(HomeworkStatus::Published, $locked->state()->status);
        self::assertNotNull($locked->state()->publishedAt);
    }

    public function testAnUnknownTaskIsLockedForNothing(): void
    {
        self::assertNull($this->homework()->withLock(
            Uuid::v7(),
            static fn (HomeworkAssignment $task): HomeworkAssignment => $task,
        ));
    }

    private function assertFree(): void
    {
        $other = DriverManager::getConnection($this->database()->connection()->getParams());
        $id = $this->task()->id()->toRfc4122();
        $other->beginTransaction();
        self::assertSame($id, $other->fetchOne(self::NOWAIT, [$id]));
        $other->rollBack();
    }

    private function homework(): DoctrineHomeworkRepository
    {
        return new DoctrineHomeworkRepository($this->database()->entityManager());
    }

    private function task(): HomeworkAssignment
    {
        self::assertInstanceOf(HomeworkAssignment::class, $this->task);

        return $this->task;
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
