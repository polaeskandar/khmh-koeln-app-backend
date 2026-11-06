<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Meetings\Infrastructure\Persistence\Repository;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Enum\SessionStatus;
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
use Symfony\Component\Uid\Uuid;

#[SkipDatabaseRollback]
final class DoctrineSessionRepositoryTest extends KernelTestCase
{
    private const string NOWAIT = 'SELECT id FROM meetings.meeting_session WHERE id = ? FOR UPDATE NOWAIT';

    private const string OPEN = <<<'SQL'
        UPDATE meetings.meeting_session SET status = 'active', opened_at = now(), opened_by = ? WHERE id = ?
        SQL;

    private ?Session $session = null;

    #[Override]
    protected function setUp(): void
    {
        $schedule = Meetings::schedule();
        $session = Meetings::session($schedule);
        $this->database()->persisted($schedule, $session);
        $this->database()->entityManager()->clear();
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
        $failure = null;

        try {
            $this->sessions()->withWriteLock(
                $this->session()->id(),
                static fn (Session $session): Session => throw new RuntimeException($session->id()->toRfc4122()),
            );
        } catch (RuntimeException $caught) {
            $failure = $caught->getMessage();
        }

        self::assertSame($this->session()->id()->toRfc4122(), $failure);

        $this->assertFree($this->otherConnection());
    }

    public function testALoadedSessionIsLockedWithWhatIsStored(): void
    {
        $id = $this->session()->id();
        $loaded = $this->sessions()->find($id);
        self::assertInstanceOf(Session::class, $loaded);
        $this->database()->connection()->executeStatement(self::OPEN, [Uuid::v7()->toRfc4122(), $id->toRfc4122()]);

        $locked = $this->sessions()->withWriteLock($id, static fn (Session $session): Session => $session);

        self::assertSame(SessionStatus::Active, $locked->progress()->status);
        self::assertNotNull($locked->progress()->opened->at);
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
