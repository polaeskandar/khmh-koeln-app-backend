<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Time\Model\LocalDate;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class BirthDate
{
    private function __construct(
        #[ORM\Column(name: 'birth_date', type: 'local_date', nullable: true)]
        public ?LocalDate $value,
    ) {
    }

    public static function none(): self
    {
        return new self(null);
    }

    public static function of(LocalDate $date, LocalDate $today): self
    {
        if ($today->isBefore($date)) {
            throw InvalidInput::field('birthDate', 'validation.birthDateInFuture');
        }

        return new self($date);
    }
}
