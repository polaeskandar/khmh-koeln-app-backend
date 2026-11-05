<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Entity;

use App\Context\Learning\Domain\Enum\LessonStatus;
use App\Context\Learning\Domain\ValueObject\LessonPlan;
use App\Context\Learning\Domain\ValueObject\LessonState;
use App\Context\Learning\Domain\ValueObject\LessonText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Context\Learning\Domain\ValueObject\MeetingChoice;
use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Library\Persistence\Mapping\TimestampedEntity;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Model\LocalDate;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'lesson', schema: 'learning')]
#[ORM\UniqueConstraint(
    name: 'lesson_meeting_unique',
    columns: ['schedule_id', 'teaching_date'],
    options: ['where' => '(archived_at IS NULL)'],
)]
#[ORM\Index(name: 'lesson_stage_date_idx', columns: ['stage_id', 'teaching_date'])]
final class Lesson extends TimestampedEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'stage_id', type: UuidType::NAME)]
    private Uuid $stageId;

    #[ORM\Embedded(MeetingLink::class, columnPrefix: false)]
    private MeetingLink $link;

    #[ORM\Column(name: 'responsible_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $responsibleId;

    #[ORM\Embedded(LessonText::class, columnPrefix: false)]
    private LessonText $text;

    #[ORM\Embedded(LessonPlan::class, columnPrefix: false)]
    private LessonPlan $plan;

    /**
     * @var list<array{label: string, url: string|null}>
     */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true, 'default' => '[]'])]
    private array $materials;

    #[ORM\Embedded(LessonState::class, columnPrefix: false)]
    private LessonState $state;

    public function __construct(Uuid $stageId, MeetingLink $link, LessonText $text, ?Uuid $responsibleId)
    {
        $this->id = Uuid::v7();
        $this->stageId = $stageId;
        $this->link = $link;
        $this->text = $text;
        $this->responsibleId = $responsibleId;
        $this->plan = new LessonPlan();
        $this->materials = Materials::none()->toArray();
        $this->state = LessonState::draft();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function stageId(): Uuid
    {
        return $this->stageId;
    }

    public function link(): MeetingLink
    {
        return $this->link;
    }

    public function text(): LessonText
    {
        return $this->text;
    }

    public function responsibleId(): ?Uuid
    {
        return $this->responsibleId;
    }

    public function plan(): LessonPlan
    {
        return $this->plan;
    }

    /**
     * @return list<array{label: string, url: string|null}>
     */
    public function materials(): array
    {
        return $this->materials;
    }

    public function describe(LessonPlan $plan, Materials $materials): void
    {
        $this->plan = $plan;
        $this->materials = $materials->toArray();
    }

    public function state(): LessonState
    {
        return $this->state;
    }

    public function archive(DateTimeImmutable $at): void
    {
        $this->state = $this->state->archivedAt($at);
    }

    public function ensureEditable(): void
    {
        if ($this->state->archivedAt !== null) {
            throw new RuleViolation('lessons.archived');
        }
    }

    public function ensureMovableTo(MeetingLink $link, LocalDate $today): void
    {
        if ($this->state->status === LessonStatus::Completed && $today->isBefore($link->teachingDate)) {
            throw MeetingChoice::refusal('lessons.taughtAfterDate');
        }
    }

    public function edit(LessonText $text, MeetingLink $link, ?Uuid $responsibleId): void
    {
        $this->text = $text;
        $this->link = $link;
        $this->responsibleId = $responsibleId;
    }

    public function prepare(LessonStatus $status): void
    {
        $this->state = $this->state->withStatus($status);
    }
}
