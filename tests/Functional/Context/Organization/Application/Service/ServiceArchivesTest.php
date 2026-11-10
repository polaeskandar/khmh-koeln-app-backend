<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Organization\Application\Service;

use App\Context\Meetings\Application\Service\SchedulePauses;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Organization\Application\Service\ServiceArchives;
use App\Context\Organization\Domain\Entity\Service;
use App\Tests\Support\CommittedRows;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use LogicException;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[SkipDatabaseRollback]
final class ServiceArchivesTest extends KernelTestCase
{
    private const string NOWAIT = 'SELECT id FROM meetings.meeting_session WHERE id = ? FOR UPDATE NOWAIT';

    private ?Service $service = null;

    private ?Session $session = null;

    #[Override]
    protected function setUp(): void
    {
        $this->service = Structure::service('Locked Youth');
        new Database(self::getContainer())->persisted($this->service);
        $schedules = new Schedules(self::getContainer());
        $this->session = $schedules->session($schedules->weekly($this->service, 'Locked evening', 1), '2026-12-07');
    }

    #[Override]
    protected function tearDown(): void
    {
        new CommittedRows(new Database(self::getContainer())->connection())->delete([
            'meetings.meeting_session' => [$this->session?->id()->toRfc4122() ?? ''],
            'meetings.meeting_schedule' => [$this->session?->schedule()->id()->toRfc4122() ?? ''],
            'organization.service' => [$this->service?->id()->toRfc4122() ?? ''],
        ]);

        parent::tearDown();
    }

    public function testTheArchiveHoldsItsScheduledSessionsUntilItCommits(): void
    {
        $other = DriverManager::getConnection(new Database(self::getContainer())->connection()->getParams());
        $sessionId = $this->session?->id()->toRfc4122() ?? '';
        $sqlState = null;

        $this->archives()->archive(
            $this->service?->id() ?? throw new LogicException('The service is set up.'),
            pause: function (Service $service) use ($other, $sessionId, &$sqlState): bool {
                $paused = $this->pauses()->pauseService($service->id());

                try {
                    $other->beginTransaction();
                    $other->fetchOne(self::NOWAIT, [$sessionId]);
                } catch (DriverException $refused) {
                    $sqlState = $refused->getSQLState();
                } finally {
                    $other->rollBack();
                }

                return $paused;
            },
        );

        $other->close();
        self::assertSame('55P03', $sqlState);
    }

    private function archives(): ServiceArchives
    {
        $archives = self::getContainer()->get(ServiceArchives::class);
        self::assertInstanceOf(ServiceArchives::class, $archives);

        return $archives;
    }

    private function pauses(): SchedulePauses
    {
        $pauses = self::getContainer()->get(SchedulePauses::class);
        self::assertInstanceOf(SchedulePauses::class, $pauses);

        return $pauses;
    }
}
