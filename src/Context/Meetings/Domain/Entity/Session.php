<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Entity;

use App\Context\Meetings\Domain\ValueObject\ErasedCounts;
use App\Context\Meetings\Domain\ValueObject\Progress;
use App\Context\Meetings\Domain\ValueObject\SessionPlan;
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

    #[ORM\Embedded(SessionPlan::class, columnPrefix: false)]
    private SessionPlan $plan;

    #[ORM\Embedded(Progress::class, columnPrefix: false)]
    private Progress $progress;

    #[ORM\Embedded(ErasedCounts::class, columnPrefix: false)]
    private ErasedCounts $erased;

    public function __construct(Schedule $schedule, LocalDate $occurrenceDate, SessionPlan $plan)
    {
        $this->id = Uuid::v7();
        $this->schedule = $schedule;
        $this->occurrenceDate = $occurrenceDate;
        $this->plan = $plan;
        $this->progress = Progress::scheduled();
        $this->erased = new ErasedCounts();
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

    public function plan(): SessionPlan
    {
        return $this->plan;
    }

    public function progress(): Progress
    {
        return $this->progress;
    }

    public function erased(): ErasedCounts
    {
        return $this->erased;
    }

    public function changeProgress(Progress $progress): void
    {
        $this->progress = $progress;
    }
}
