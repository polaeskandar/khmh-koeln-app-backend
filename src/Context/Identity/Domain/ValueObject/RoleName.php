<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use App\Library\Strings\Model\NameKey;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class RoleName
{
    private const int MAX_LENGTH = 80;

    private function __construct(
        #[ORM\Column(name: 'name', length: self::MAX_LENGTH)]
        public string $value,
        #[ORM\Column(name: 'name_key', type: Types::TEXT)]
        public string $key,
    ) {
    }

    public static function of(string $value): self
    {
        $clean = InputText::of($value)->collapsed();

        if ($clean->isEmpty()) {
            throw InvalidInput::field('name', 'roles.nameRequired');
        }

        if ($clean->length() > self::MAX_LENGTH) {
            throw InvalidInput::tooLong('name', self::MAX_LENGTH);
        }

        return new self($clean->toString(), NameKey::of($clean->toString()));
    }
}
