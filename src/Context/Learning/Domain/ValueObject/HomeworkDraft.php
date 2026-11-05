<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;

final readonly class HomeworkDraft
{
    private const string RESOURCES = 'resources';

    /**
     * @param list<array<string, string|null>>|null $resources
     */
    public function __construct(
        public ?string $title,
        public ?string $instructions,
        public ?string $dueDate,
        public ?array $resources,
    ) {
    }

    public function contentFor(Lesson $lesson, ?HomeworkAssignment $stored): HomeworkContent
    {
        $text = $stored?->text();
        $title = InputText::of($this->title ?? $text?->title)->collapsed()->toString();
        $instructions = InputText::of($this->instructions ?? $text?->instructions)->trimmed()->toString();
        $rows = $this->resources ?? $stored?->resources() ?? [];

        $errors = [
            ...HomeworkTitleRule::errors($title),
            ...HomeworkInstructionsRule::errors($instructions),
            ...HomeworkDueDate::errors($this->dueDate, $text?->dueDate, $lesson->link()->teachingDate),
            ...self::resourceErrors($rows),
        ];

        if ($errors !== []) {
            throw new InvalidInput($errors);
        }

        return new HomeworkContent(
            HomeworkText::of($title, $instructions, HomeworkDueDate::of($this->dueDate, $text?->dueDate)),
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
