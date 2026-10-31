<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class DisplayName
{
    private const int MAX_LENGTH = 120;

    private function __construct(
        #[ORM\Column(name: 'display_name', length: self::MAX_LENGTH)]
        public string $value,
    ) {
    }

    public static function of(string $value): self
    {
        $clean = InputText::of($value)->collapsed();

        if ($clean->isEmpty()) {
            throw InvalidInput::field('displayName', 'accounts.nameRequired');
        }

        if ($clean->length() > self::MAX_LENGTH) {
            throw InvalidInput::tooLong('displayName', self::MAX_LENGTH);
        }

        return new self($clean->toString());
    }
}
