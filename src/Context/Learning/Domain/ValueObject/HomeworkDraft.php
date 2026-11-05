<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\Lesson;
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

        $errors = [
            ...HomeworkTitleRule::errors($title),
            ...HomeworkInstructionsRule::errors($instructions),
            ...HomeworkDueDateRule::errors($this->dueDate, $storedDueDate, $lesson->link()->teachingDate),
            ...self::resourceErrors($rows),
        ];

        if ($errors !== []) {
            throw new InvalidInput($errors);
        }

        return new HomeworkContent(
            HomeworkText::of($title, $instructions, HomeworkDueDate::of($this->dueDate)),
            Materials::of($rows, self::RESOURCES),
        );
    }

    /**
     * @param list<array<array-key, string|null>> $rows
     *
     * @return array<string, array{code: string, params?: array<string, string|int|float|bool|null>}>
     */
    private static function resourceErrors(array $rows): array
    {
        try {
            Materials::of($rows, self::RESOURCES);
        } catch (InvalidInput $invalid) {
            return $invalid->fieldErrors;
        }

        return [];
    }
}
