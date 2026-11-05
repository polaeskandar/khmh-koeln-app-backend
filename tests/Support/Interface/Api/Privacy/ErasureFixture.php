<?php

declare(strict_types=1);

namespace App\Tests\Support\Interface\Api\Privacy;

use App\Context\Attendance\Domain\Entity\Attendance;
use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Person;
use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Context\Privacy\Domain\Enum\RequestChannel;
use App\Context\Privacy\Domain\Enum\RequestKind;
use App\Context\Privacy\Domain\ValueObject\Receipt;
use App\Context\Privacy\Domain\ValueObject\RequestSubject;
use App\Library\Time\Model\LocalDate;
use App\Library\Types\Model\Integers;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class ErasureFixture
{
    private const string CANCELED = "status = 'canceled', cancel_reason = ?";

    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * @return array{Person, Service, Stage}
     */
    public function kiro(): array
    {
        $service = Structure::service();
        $third = Structure::stage($service);
        $first = Structure::stage($service, '1st Class', 1);
        new Database($this->container)->persisted($service, $third, $first);
        $rosters = new Rosters($this->container);
        $kiro = $rosters->person('Kiro', 'Nashed');
        $rosters->place($kiro, $service, $third, '2026-09-01');
        $schedules = new Schedules($this->container);
        $class = $schedules->weekly($service, 'Sunday class', 7, stage: $third);
        $this->canceled($class, '2026-11-01', 'Kiro was there but not ticked.');
        $this->canceled($schedules->weekly($service, 'First class', 7, stage: $first), '2026-11-01', 'Kiro is ill');
        $this->canceled($schedules->weekly($service, 'Service evening', 5), '2026-10-30', 'Kirollosx was late');
        $this->marks($rosters, $service, $third, $class, $kiro);

        $lesson = new Lessons($this->container)->stored($third, $class, '2026-11-15');

        new Database($this->container)
            ->connection()
            ->update('learning.lesson', ['notes' => 'Ask Kiro to read.'], ['id' => $lesson->id()->toRfc4122()]);

        $families = new Families($this->container);
        $families->link($families->account('hoda@example.org'), $kiro);

        return [$kiro, $service, $third];
    }

    public function request(RequestKind $kind, ?Person $person, ?string $accountId = null): string
    {
        $request = new DataRequest(
            $kind,
            new RequestSubject($person?->id(), $accountId === null ? null : Uuid::fromString($accountId)),
            new Receipt('Hoda Farag', RequestChannel::Letter, new DateTimeImmutable('2026-11-02T12:00:00+01:00')),
            LocalDate::of('2026-12-02'),
            'Asked by letter.',
        );

        new Database($this->container)->persisted($request);

        return $request->id()->toRfc4122();
    }

    public function numberOf(string $sql): int
    {
        return Integers::of(
            new Database($this->container)
                ->connection()
                ->fetchOne($sql),
        );
    }

    private function canceled(Schedule $schedule, string $date, string $reason): void
    {
        $session = new Schedules($this->container)->session($schedule, $date);

        new Database($this->container)
            ->connection()
            ->executeStatement('UPDATE meetings.meeting_session SET ' . self::CANCELED . ' WHERE id = ?', [
                $reason,
                $session->id()->toRfc4122(),
            ]);
    }

    private function marks(Rosters $rosters, Service $service, Stage $third, Schedule $class, Person $kiro): void
    {
        $mina = $rosters->person('Mina', 'Hanna');
        $rosters->place($mina, $service, $third, '2026-09-01');

        $session = new Schedules($this->container)->session(
            $class,
            '2026-10-25',
            "status = 'completed', opened_at = created_at, closed_at = created_at",
        );

        $at = new DateTimeImmutable('2026-10-25T12:00:00+01:00');
        $present = Attendance::record($session->id(), $kiro->id(), $at, null);
        $removed = Attendance::record($session->id(), $mina->id(), $at, null);
        $removed->remove(null, new DateTimeImmutable('2026-10-25T12:05:00+01:00'), 'Kiro ticked Mina by mistake');
        new Database($this->container)->persisted($present, $removed);
    }
}
