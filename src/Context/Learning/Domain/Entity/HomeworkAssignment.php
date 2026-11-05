<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Entity;

use App\Context\Learning\Domain\Enum\HomeworkStatus;
use App\Context\Learning\Domain\ValueObject\HomeworkState;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Library\Persistence\Mapping\TimestampedEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'homework_assignment', schema: 'learning')]
#[ORM\Index(name: 'homework_assignment_stage_status_idx', columns: ['stage_id', 'status'])]
final class HomeworkAssignment extends TimestampedEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'lesson_id', nullable: false, onDelete: 'RESTRICT')]
    private Lesson $lesson;

    #[ORM\Column(name: 'stage_id', type: UuidType::NAME)]
    private Uuid $stageId;

    #[ORM\Embedded(HomeworkText::class, columnPrefix: false)]
    private HomeworkText $text;

    /**
     * @var list<array{url: string|null, label: string}>
     */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true, 'default' => '[]'])]
    private array $resources;

    #[ORM\Embedded(HomeworkState::class, columnPrefix: false)]
    private HomeworkState $state;

    #[ORM\Column(name: 'created_by', type: UuidType::NAME, nullable: true)]
    private ?Uuid $createdBy;

    public function __construct(Lesson $lesson, HomeworkText $text, Materials $resources, ?Uuid $createdBy)
    {
        $this->id = Uuid::v7();
        $this->lesson = $lesson;
        $this->stageId = $lesson->stageId();
        $this->text = $text;
        $this->resources = $resources->toArray();
        $this->state = HomeworkState::draft();
        $this->createdBy = $createdBy;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function lesson(): Lesson
    {
        return $this->lesson;
    }

    public function stageId(): Uuid
    {
        return $this->stageId;
    }

    public function text(): HomeworkText
    {
        return $this->text;
    }

    /**
     * @return list<array{url: string|null, label: string}>
     */
    public function resources(): array
    {
        return $this->resources;
    }

    public function state(): HomeworkState
    {
        return $this->state;
    }

    public function createdBy(): ?Uuid
    {
        return $this->createdBy;
    }

    public function isVisibleToParents(): bool
    {
        return $this->state->status === HomeworkStatus::Published;
    }

    public function changeState(HomeworkState $state): void
    {
        $this->state = $state;
    }

    public function edit(HomeworkText $text, Materials $resources): void
    {
        $this->text = $text;
        $this->resources = $resources->toArray();
    }
}
