<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Meetings\Infrastructure\Persistence\Repository;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Tests\Support\CommittedRows;
use App\Tests\Support\Context\Meetings\Domain\Entity\Meetings;
use App\Tests\Support\Database;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Override;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[SkipDatabaseRollback]
final class DoctrineSessionRepositoryTest extends KernelTestCase
{
    private const string NOWAIT = 'SELECT id FROM meetings.meeting_session WHERE id = ? FOR UPDATE NOWAIT';

    private ?Session $session = null;

    #[Override]
    protected function setUp(): void
    {
        $schedule = Meetings::schedule();
        $session = Meetings::session($schedule);
        $this->database()->persisted($schedule, $session);
        $this->session = $session;
    }

    #[Override]
    protected function tearDown(): void
    {
        $session = $this->session();

        new CommittedRows($this->database()->connection())->delete([
            'meetings.meeting_session' => [$session->id()->toRfc4122()],
            'meetings.meeting_schedule' => [$session->schedule()->id()->toRfc4122()],
        ]);

        parent::tearDown();
    }

    public function testWhileTheLockIsHeldAnotherConnectionCannotTakeTheRow(): void
    {
        $other = $this->otherConnection();

        $this->sessions()->withWriteLock($this->session()->id(), static function (Session $session) use (
            $other,
        ): Session {
            $sqlState = null;

            try {
                $other->beginTransaction();
                $other->fetchOne(self::NOWAIT, [$session->id()->toRfc4122()]);
            } catch (DriverException $refused) {
                $sqlState = $refused->getSQLState();
            } finally {
                $other->rollBack();
            }

            self::assertSame('55P03', $sqlState);

            return $session;
        });

        $this->assertFree($other);
    }

    public function testAFailureReleasesTheLock(): void
    {
        try {
            $this->sessions()->withWriteLock(
                $this->session()->id(),
                static fn (Session $session): Session => throw new RuntimeException($session->id()->toRfc4122()),
            );
        } catch (RuntimeException $failure) {
            self::assertSame($this->session()->id()->toRfc4122(), $failure->getMessage());
        }

        $this->assertFree($this->otherConnection());
    }

    private function assertFree(Connection $other): void
    {
        $other->beginTransaction();
        self::assertSame($this->session()->id()->toRfc4122(), $other->fetchOne(self::NOWAIT, [$this->session()->id()->toRfc4122()]));
        $other->rollBack();
    }

    private function otherConnection(): Connection
    {
        return DriverManager::getConnection($this->database()->connection()->getParams());
    }

    private function sessions(): SessionRepositoryInterface
    {
        $sessions = self::getContainer()->get(SessionRepositoryInterface::class);
        self::assertInstanceOf(SessionRepositoryInterface::class, $sessions);

        return $sessions;
    }

    private function session(): Session
    {
        self::assertInstanceOf(Session::class, $this->session);

        return $this->session;
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
