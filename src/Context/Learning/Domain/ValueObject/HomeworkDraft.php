<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Validation\HomeworkRule;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use App\Library\Time\Model\LocalDate;

final readonly class HomeworkDraft
{
    private const string RESOURCES = 'resources';

    /**
     * @param list<array<string, string|null>> $resources
     */
    public function __construct(
        public ?string $title,
        public ?string $instructions,
        public ?string $dueDate,
        public array $resources,
    ) {
    }

    public function contentFor(Lesson $lesson, ?LocalDate $storedDueDate): HomeworkContent
    {
        $title = InputText::of($this->title)->collapsed()->toString();
        $instructions = InputText::of($this->instructions)->trimmed()->toString();
        $rows = $this->resources;
        $teachingDate = $lesson->link()->teachingDate;

        InvalidInput::all(
            static fn () => HomeworkRule::ensureTitle($title),
            static fn () => HomeworkRule::ensureInstructions($instructions),
            fn () => HomeworkRule::ensureDueDate(HomeworkDueDate::of($this->dueDate), $storedDueDate, $teachingDate),
            static fn (): Materials => Materials::of($rows, self::RESOURCES),
        );

        return new HomeworkContent(
            HomeworkText::of($title, $instructions, HomeworkDueDate::of($this->dueDate)),
            Materials::of($rows, self::RESOURCES),
        );
    }
}
