<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\Validation\UsernameRule;
use App\Library\Strings\Model\InputText;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Username
{
    private function __construct(
        #[ORM\Column(name: 'username', length: UsernameRule::MAX_LENGTH)]
        public string $value,
    ) {
    }

    public static function of(string $typed, string $field = 'username'): self
    {
        $username = self::normalized($typed);
        UsernameRule::ensureValid($username, $field);

        return new self($username);
    }

    public static function normalized(string $typed): string
    {
        return InputText::of($typed)->trimmed()->compatibilityComposed()->lowered()->toString();
    }
}
