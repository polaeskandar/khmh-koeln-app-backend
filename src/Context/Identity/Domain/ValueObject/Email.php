<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use App\Library\Strings\Model\Text;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Email
{
    private const int MAX_LENGTH = 254;

    private const string SHAPE = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/u';

    private function __construct(
        #[ORM\Column(name: 'email', length: self::MAX_LENGTH)]
        public string $value,
    ) {
    }

    public static function of(string $value): self
    {
        $email = self::lowered($value);

        if ($email->isEmpty()) {
            throw InvalidInput::field('email', 'accounts.emailRequired');
        }

        if (!Text::of($email->toString())->matches(self::SHAPE)) {
            throw InvalidInput::field('email', 'accounts.emailInvalid');
        }

        if ($email->length() > self::MAX_LENGTH) {
            throw InvalidInput::tooLong('email', self::MAX_LENGTH);
        }

        return new self($email->toString());
    }

    public static function normalized(string $value): string
    {
        return self::lowered($value)->toString();
    }

    private static function lowered(string $value): InputText
    {
        return InputText::of($value)->trimmed()->lowered();
    }
}
