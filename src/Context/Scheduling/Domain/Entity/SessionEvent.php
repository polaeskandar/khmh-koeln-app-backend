<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Entity;

use App\Context\Scheduling\Domain\Enum\SessionEventType;
use App\Context\Scheduling\Domain\ValueObject\Actor;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'session_event', schema: 'scheduling')]
#[ORM\Index(name: 'session_event_session_occurred_idx', columns: ['session_id', 'occurred_at'])]
final class SessionEvent
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'session_id', nullable: false, onDelete: 'RESTRICT')]
    private readonly Session $session;

    #[ORM\Column(length: 8, enumType: SessionEventType::class)]
    private readonly SessionEventType $type;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private readonly DateTimeImmutable $occurredAt;

    #[ORM\Embedded(Actor::class, columnPrefix: false)]
    private readonly Actor $actor;

    #[ORM\Column(length: 200, nullable: true)]
    private readonly ?string $note;

    public function __construct(
        Session $session,
        SessionEventType $type,
        DateTimeImmutable $occurredAt,
        Actor $actor,
        ?string $note,
    ) {
        $this->id = Uuid::v7();
        $this->session = $session;
        $this->type = $type;
        $this->occurredAt = $occurredAt;
        $this->actor = $actor;
        $this->note = $note;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function session(): Session
    {
        return $this->session;
    }

    public function type(): SessionEventType
    {
        return $this->type;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function actor(): Actor
    {
        return $this->actor;
    }

    public function note(): ?string
    {
        return $this->note;
    }
}
