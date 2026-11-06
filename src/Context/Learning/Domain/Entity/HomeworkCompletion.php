<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'homework_completion', schema: 'learning')]
#[ORM\UniqueConstraint(
    name: 'homework_completion_current_unique',
    columns: ['assignment_id', 'person_id'],
    options: ['where' => '(undone_at IS NULL)'],
)]
#[ORM\Index(name: 'homework_completion_person_idx', columns: ['person_id'])]
final class HomeworkCompletion
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'assignment_id', nullable: false, onDelete: 'RESTRICT')]
    private HomeworkAssignment $assignment;

    #[ORM\Column(name: 'person_id', type: UuidType::NAME)]
    private Uuid $personId;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private DateTimeImmutable $completedAt;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $completedBy;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $undoneAt = null;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $undoneBy = null;

    public function __construct(
        HomeworkAssignment $assignment,
        Uuid $personId,
        DateTimeImmutable $completedAt,
        ?Uuid $completedBy,
    ) {
        $this->id = Uuid::v7();
        $this->assignment = $assignment;
        $this->personId = $personId;
        $this->completedAt = $completedAt;
        $this->completedBy = $completedBy;
    }

    public function undo(DateTimeImmutable $at, ?Uuid $by): void
    {
        $this->undoneAt = $at;
        $this->undoneBy = $by;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function assignment(): HomeworkAssignment
    {
        return $this->assignment;
    }

    public function personId(): Uuid
    {
        return $this->personId;
    }

    public function completedBy(): ?Uuid
    {
        return $this->completedBy;
    }

    public function completedAt(): DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function undoneAt(): ?DateTimeImmutable
    {
        return $this->undoneAt;
    }

    public function undoneBy(): ?Uuid
    {
        return $this->undoneBy;
    }
}
