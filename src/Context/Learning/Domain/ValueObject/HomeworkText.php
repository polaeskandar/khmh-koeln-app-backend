<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Library\Strings\Model\InputText;
use App\Library\Time\Model\LocalDate;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class HomeworkText
{
    private function __construct(
        #[ORM\Column(name: 'title', length: 120)]
        public string $title,
        #[ORM\Column(name: 'instructions', type: Types::TEXT)]
        public string $instructions,
        #[ORM\Column(name: 'due_date', type: 'local_date', nullable: true)]
        public ?LocalDate $dueDate,
    ) {
    }

    public static function of(string $title, string $instructions, ?LocalDate $dueDate): self
    {
        return new self(
            InputText::of($title)->collapsed()->toString(),
            InputText::of($instructions)->trimmed()->toString(),
            $dueDate,
        );
    }

    public function earliestDueDate(LocalDate $teachingDate): LocalDate
    {
        $dueDate = $this->dueDate ?? $teachingDate;

        return $dueDate->isBefore($teachingDate) ? $dueDate : $teachingDate;
    }
}
