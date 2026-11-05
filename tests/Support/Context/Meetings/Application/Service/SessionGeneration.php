<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Enum\RosterMode;
use App\Context\Meetings\Domain\ValueObject\DateSpan;
use App\Context\Meetings\Domain\ValueObject\MeetingDetails;
use App\Context\Meetings\Domain\ValueObject\Recurrence;
use App\Context\Meetings\Domain\ValueObject\Roster;
use App\Context\Meetings\Domain\ValueObject\TimeSlot;
use App\Library\Time\Model\LocalDate;
use App\Library\Time\Model\LocalTime;
use App\Tests\Support\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class SessionGeneration
{
    private const string REFUSE_SESSION = <<<'SQL'
        CREATE FUNCTION meetings.refuse_session() RETURNS trigger LANGUAGE plpgsql AS $$
        BEGIN RAISE EXCEPTION 'forced failure'; END $$
        SQL;

    private const string REFUSE_SESSIONS_OF =
        'CREATE TRIGGER refuse_session BEFORE INSERT ON meetings.meeting_session '
            . "FOR EACH ROW WHEN (NEW.schedule_id = '%s') EXECUTE FUNCTION meetings.refuse_session()";

    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public static function sundays(?Uuid $serviceId = null, ?Uuid $stageId = null, ?string $endsOn = null): Schedule
    {
        return new Schedule(
            new Roster($serviceId ?? Uuid::v7(), $stageId, RosterMode::Expected),
            new MeetingDetails('Sunday class', description: null, location: null),
            Recurrence::weekly(
                1,
                [7],
                new DateSpan(LocalDate::of('2026-09-06'), $endsOn === null ? null : LocalDate::of($endsOn)),
            ),
            new TimeSlot(LocalTime::of('12:00'), LocalTime::of('13:30')),
        );
    }

    /**
     * @return list<string>
     */
    public function sessionDates(): array
    {
        return array_map(
            strval(...),
            $this
                ->database()
                ->connection()
                ->fetchFirstColumn('SELECT occurrence_date FROM meetings.meeting_session ORDER BY occurrence_date'),
        );
    }

    public function refuseSessionsOf(Schedule $schedule): void
    {
        $connection = $this->database()->connection();

        $connection->executeStatement(self::REFUSE_SESSION);
        $connection->executeStatement(sprintf(self::REFUSE_SESSIONS_OF, $schedule->id()->toRfc4122()));
    }

    private function database(): Database
    {
        return new Database($this->container);
    }
}
