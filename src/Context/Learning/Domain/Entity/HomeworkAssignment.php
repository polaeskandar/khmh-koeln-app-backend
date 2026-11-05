<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Entity;

use App\Context\Learning\Domain\Enum\HomeworkStatus;
use App\Context\Learning\Domain\ValueObject\HomeworkState;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Library\Persistence\Mapping\TimestampedEntity;
use App\Library\Problem\Exception\RuleViolation;
use DateTimeImmutable;
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
     * @var list<array{label: string, url: string|null}>
     */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true, 'default' => '[]'])]
    private array $resources;

    #[ORM\Embedded(HomeworkState::class, columnPrefix: false)]
    private HomeworkState $state;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(name: 'created_by', type: UuidType::NAME, nullable: true)]
    private ?Uuid $createdBy;

    private function __construct(Lesson $lesson, HomeworkText $text, Materials $resources, ?Uuid $createdBy)
    {
        $this->id = Uuid::v7();
        $this->lesson = $lesson;
        $this->stageId = $lesson->stageId();
        $this->text = $text;
        $this->resources = $resources->toArray();
        $this->state = HomeworkState::draft();
        $this->createdBy = $createdBy;
    }

    public static function draft(Lesson $lesson, HomeworkText $text, Materials $resources, ?Uuid $createdBy): self
    {
        return new self($lesson, $text, $resources, $createdBy);
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
     * @return list<array{label: string, url: string|null}>
     */
    public function resources(): array
    {
        return $this->resources;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function state(): HomeworkState
    {
        return $this->state;
    }

    public function createdBy(): ?Uuid
    {
        return $this->createdBy;
    }

    public function ensureEditable(): void
    {
        $this->state->ensureNotArchived('homework.archivedNoEdit');
    }

    public function edit(HomeworkText $text, Materials $resources): void
    {
        $this->text = $text;
        $this->resources = $resources->toArray();
    }

    public function publish(DateTimeImmutable $at): bool
    {
        $this->state->ensureNotArchived('homework.archivedNoPublish');
        $this->ensureLessonOpen();

        if ($this->isVisibleToParents()) {
            return false;
        }

        $this->state = $this->state->publishedAt($at);

        return true;
    }

    public function archive(DateTimeImmutable $at): bool
    {
        $archived = $this->state->archived($at);
        $changed = $archived !== $this->state;
        $this->state = $archived;

        return $changed;
    }

    public function unpublish(): void
    {
        $this->state = $this->state->drafted();
    }

    public function restore(): void
    {
        if ($this->state->archivedAt === null) {
            throw new RuleViolation('homework.notArchived');
        }

        $this->ensureLessonOpen();
        $this->state = $this->state->restored();
    }

    public function isVisibleToParents(): bool
    {
        return $this->state->status === HomeworkStatus::Published;
    }

    private function ensureLessonOpen(): void
    {
        if ($this->lesson->state()->archivedAt !== null) {
            throw new RuleViolation('homework.lessonArchived');
        }
    }
}
