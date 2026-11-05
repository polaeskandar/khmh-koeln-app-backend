<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Entity;

use App\Context\Meetings\Domain\ValueObject\MeetingDetails;
use App\Context\Meetings\Domain\ValueObject\Recurrence;
use App\Context\Meetings\Domain\ValueObject\Roster;
use App\Context\Meetings\Domain\ValueObject\TimeSlot;
use App\Library\Persistence\Mapping\TimestampedEntity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'meeting_schedule', schema: 'meetings')]
final class Schedule extends TimestampedEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Embedded(Roster::class, columnPrefix: false)]
    private Roster $roster;

    #[ORM\Embedded(MeetingDetails::class, columnPrefix: false)]
    private MeetingDetails $details;

    #[ORM\Embedded(Recurrence::class, columnPrefix: false)]
    private Recurrence $recurrence;

    #[ORM\Embedded(TimeSlot::class, columnPrefix: false)]
    private TimeSlot $timeSlot;

    #[ORM\Column]
    private bool $active = true;

    public function __construct(Roster $roster, MeetingDetails $details, Recurrence $recurrence, TimeSlot $timeSlot)
    {
        $this->id = Uuid::v7();
        $this->roster = $roster;
        $this->details = $details;
        $this->recurrence = $recurrence;
        $this->timeSlot = $timeSlot;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function roster(): Roster
    {
        return $this->roster;
    }

    public function details(): MeetingDetails
    {
        return $this->details;
    }

    public function recurrence(): Recurrence
    {
        return $this->recurrence;
    }

    public function timeSlot(): TimeSlot
    {
        return $this->timeSlot;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
