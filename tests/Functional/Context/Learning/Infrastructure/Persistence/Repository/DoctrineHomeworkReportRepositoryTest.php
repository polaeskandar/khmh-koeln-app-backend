<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Learning\Infrastructure\Persistence\Repository;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\LessonText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Context\Learning\Infrastructure\Persistence\Repository\DoctrineHomeworkReportRepository;
use App\Context\Learning\Infrastructure\Persistence\Repository\DoctrineHomeworkRepository;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\CommittedRows;
use App\Tests\Support\Database;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

#[SkipDatabaseRollback]
final class DoctrineHomeworkReportRepositoryTest extends KernelTestCase
{
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
            HomeworkText::of('Learn the verse', 'Read it.', null),
            Materials::none(),
            null,
        );

        new Database(self::getContainer())->persisted($lesson, $task);
        $this->task = $task;
    }

    #[Override]
    protected function tearDown(): void
    {
        $task = $this->task();
        $connection = new Database(self::getContainer())->connection();
        $connection->executeStatement('DELETE FROM learning.homework_completion WHERE assignment_id = ?', [$task->id()->toRfc4122()]);

        new CommittedRows($connection)->delete([
            'learning.homework_assignment' => [$task->id()->toRfc4122()],
            'learning.lesson' => [$task->lesson()->id()->toRfc4122()],
        ]);

        parent::tearDown();
    }

    public function testACurrentReportWinsAndTheEntityManagerStaysOpen(): void
    {
        $child = Uuid::v7();
        $reports = $this->reports();

        self::assertTrue($reports->reportDone(
            $this->task()->id(),
            $child,
            new DateTimeImmutable('2026-11-07T10:00:00+01:00'),
            null,
        ));

        self::assertFalse($reports->reportDone(
            $this->task()->id(),
            $child,
            new DateTimeImmutable('2026-11-08T10:00:00+01:00'),
            Uuid::v7(),
        ));

        $current = $reports->currentReport($this->task()->id(), $child);

        self::assertSame(
            '2026-11-07T09:00:00+00:00',
            $current?->completedAt->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
        );

        self::assertNull($current?->completedBy);

        $entityManager = new Database(self::getContainer())->entityManager();
        self::assertTrue($entityManager->isOpen());
        self::assertNotNull($entityManager->find(HomeworkAssignment::class, $this->task()->id()));

        $reports->undo($this->task()->id(), $child, new DateTimeImmutable('2026-11-08T11:00:00+01:00'), null);
        self::assertNull($reports->currentReport($this->task()->id(), $child));
    }

    public function testAReportWaitsForTheTaskRowAndWritesNothingOnTimeout(): void
    {
        $database = new Database(self::getContainer());
        $other = DriverManager::getConnection($database->connection()->getParams());
        $other->beginTransaction();
        $other->fetchOne('SELECT id FROM learning.homework_assignment WHERE id = ? FOR UPDATE', [$this->task()->id()->toRfc4122()]);
        $database->connection()->executeStatement("SET lock_timeout = '200ms'");
        $database->entityManager()->clear();
        $sqlState = null;

        try {
            new DoctrineHomeworkRepository(
                $database->entityManager(),
            )->withWriteLock($this->task()->id(), fn (HomeworkAssignment $task): HomeworkAssignment => $this->reports()->reportDone(
                $task->id(),
                Uuid::v7(),
                new DateTimeImmutable(),
                null,
            )
                    ? $task
                    : $task);
        } catch (DriverException $refused) {
            $sqlState = $refused->getSQLState();
        } finally {
            $other->rollBack();
            $other->close();
            $database->connection()->executeStatement('SET lock_timeout = 0');
        }

        self::assertSame('55P03', $sqlState);
        self::assertSame(0, $database->connection()->fetchOne('SELECT count(*) FROM learning.homework_completion WHERE assignment_id = ?', [$this->task()->id()->toRfc4122()]));
    }

    private function reports(): DoctrineHomeworkReportRepository
    {
        return new DoctrineHomeworkReportRepository(new Database(self::getContainer())->entityManager());
    }

    private function task(): HomeworkAssignment
    {
        self::assertInstanceOf(HomeworkAssignment::class, $this->task);

        return $this->task;
    }
}
