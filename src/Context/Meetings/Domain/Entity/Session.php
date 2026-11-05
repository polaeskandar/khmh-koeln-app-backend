<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Entity;

use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Context\Meetings\Domain\ValueObject\AttendanceRules;
use App\Context\Meetings\Domain\ValueObject\Progress;
use App\Context\Meetings\Domain\ValueObject\Stamp;
use App\Library\Persistence\Mapping\CreatedAtEntity;
use App\Library\Time\Model\LocalDate;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'meeting_session', schema: 'meetings')]
#[ORM\UniqueConstraint(name: 'meeting_session_occurrence_unique', columns: ['schedule_id', 'occurrence_date'])]
#[ORM\Index(name: 'meeting_session_occurrence_date_idx', columns: ['occurrence_date'])]
final class Session extends CreatedAtEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'schedule_id', nullable: false, onDelete: 'RESTRICT')]
    private Schedule $schedule;

    #[ORM\Column(type: 'local_date')]
    private LocalDate $occurrenceDate;

    #[ORM\Embedded(Progress::class, columnPrefix: false)]
    private Progress $progress;

    public function __construct(Schedule $schedule, LocalDate $occurrenceDate)
    {
        $this->id = Uuid::v7();
        $this->schedule = $schedule;
        $this->occurrenceDate = $occurrenceDate;
        $this->progress = Progress::scheduled();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function schedule(): Schedule
    {
        return $this->schedule;
    }

    public function occurrenceDate(): LocalDate
    {
        return $this->occurrenceDate;
    }

    public function progress(): Progress
    {
        return $this->progress;
    }

    public function open(LocalDate $today, Stamp $stamp): bool
    {
        if ($this->progress->status === SessionStatus::Active) {
            return false;
        }

        AttendanceRules::ensureOpenable($this->progress->status, $this->occurrenceDate, $today);
        $this->progress = $this->progress->opened($stamp);

        return true;
    }

    public function close(Stamp $stamp): bool
    {
        if ($this->progress->status === SessionStatus::Completed) {
            return false;
        }

        AttendanceRules::ensureClosable($this->progress->status);
        $this->progress = $this->progress->closed($stamp);

        return true;
    }

    public function reopen(): bool
    {
        AttendanceRules::ensureReopenable($this->progress->status);
        $this->progress = $this->progress->reopened();

        return true;
    }
}
