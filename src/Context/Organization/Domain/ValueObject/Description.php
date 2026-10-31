<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Description
{
    private function __construct(
        #[ORM\Column(name: 'description', type: Types::TEXT, nullable: true)]
        public ?string $value,
    ) {
    }

    public static function of(?string $value, int $max): self
    {
        $clean = InputText::of($value ?? '')->trimmed();
        if ($clean->length() > $max) {
            throw new InvalidInput(['description' => ['code' => 'validation.tooLong', 'params' => ['max' => $max]]]);
        }

        return new self($clean->isEmpty() ? null : $clean->toString());
    }
}
