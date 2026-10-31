<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Entity;

use App\Context\Meetings\Domain\ValueObject\Heading;
use App\Context\Meetings\Domain\ValueObject\Progress;
use App\Context\Meetings\Domain\ValueObject\Roster;
use App\Context\Meetings\Domain\ValueObject\Timing;
use App\Library\Persistence\Mapping\CreatedAtEntity;
use App\Library\Time\Model\LocalDate;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'meeting_session', schema: 'meetings')]
#[ORM\UniqueConstraint(name: 'meeting_session_occurrence_unique', columns: ['schedule_id', 'occurrence_date'])]
#[ORM\Index(name: 'meeting_session_starts_at_idx', columns: ['starts_at'])]
final class Session extends CreatedAtEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private readonly Uuid $id;

    #[ORM\Embedded(Roster::class, columnPrefix: false)]
    private Roster $roster;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'schedule_id', nullable: true, onDelete: 'RESTRICT')]
    private readonly ?Schedule $schedule;

    #[ORM\Column(type: 'local_date')]
    private readonly LocalDate $occurrenceDate;

    #[ORM\Embedded(Heading::class, columnPrefix: false)]
    private Heading $heading;

    #[ORM\Embedded(Timing::class, columnPrefix: false)]
    private Timing $timing;

    #[ORM\Embedded(Progress::class, columnPrefix: false)]
    private Progress $progress;

    private function __construct(
        Roster $roster,
        ?Schedule $schedule,
        LocalDate $occurrenceDate,
        Heading $heading,
        Timing $timing,
    ) {
        $this->id = Uuid::v7();
        $this->roster = $roster;
        $this->schedule = $schedule;
        $this->occurrenceDate = $occurrenceDate;
        $this->heading = $heading;
        $this->timing = $timing;
        $this->progress = Progress::scheduled();
    }

    public static function ofSchedule(Schedule $schedule, LocalDate $occurrenceDate, Timing $timing): self
    {
        $details = $schedule->details();

        return new self(
            $schedule->roster(),
            $schedule,
            $occurrenceDate,
            new Heading($details->name, $details->location),
            $timing,
        );
    }

    public static function oneOff(Roster $roster, LocalDate $date, Heading $heading, Timing $timing): self
    {
        return new self($roster, schedule: null, occurrenceDate: $date, heading: $heading, timing: $timing);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function roster(): Roster
    {
        return $this->roster;
    }

    public function schedule(): ?Schedule
    {
        return $this->schedule;
    }

    public function occurrenceDate(): LocalDate
    {
        return $this->occurrenceDate;
    }

    public function heading(): Heading
    {
        return $this->heading;
    }

    public function timing(): Timing
    {
        return $this->timing;
    }

    public function progress(): Progress
    {
        return $this->progress;
    }
}
