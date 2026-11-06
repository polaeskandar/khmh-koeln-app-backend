<?php

declare(strict_types=1);

namespace App\Context\Attendance\Domain\Entity;

use App\Context\Attendance\Domain\ValueObject\Removal;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'attendance', schema: 'attendance')]
#[ORM\UniqueConstraint(
    name: 'uniq_attendance_current',
    columns: ['session_id', 'person_id'],
    options: ['where' => '(removed_at IS NULL)'],
)]
#[ORM\Index(name: 'attendance_session_idx', columns: ['session_id'])]
#[ORM\Index(name: 'attendance_person_idx', columns: ['person_id'])]
final class Attendance
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'session_id', type: UuidType::NAME)]
    private readonly Uuid $sessionId;

    #[ORM\Column(name: 'person_id', type: UuidType::NAME)]
    private readonly Uuid $personId;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private readonly DateTimeImmutable $recordedAt;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private readonly ?Uuid $recordedBy;

    #[ORM\Embedded(Removal::class, columnPrefix: false)]
    private Removal $removal;

    public function __construct(Uuid $sessionId, Uuid $personId, DateTimeImmutable $recordedAt, ?Uuid $recordedBy)
    {
        $this->id = Uuid::v7();
        $this->sessionId = $sessionId;
        $this->personId = $personId;
        $this->recordedAt = $recordedAt;
        $this->recordedBy = $recordedBy;
        $this->removal = Removal::none();
    }

    public function remove(Removal $removal): void
    {
        $this->removal = $removal;
    }

    public function isCurrent(): bool
    {
        return !$this->removal->happened();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function sessionId(): Uuid
    {
        return $this->sessionId;
    }

    public function personId(): Uuid
    {
        return $this->personId;
    }

    public function recordedAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function recordedBy(): ?Uuid
    {
        return $this->recordedBy;
    }

    public function removal(): Removal
    {
        return $this->removal;
    }
}
