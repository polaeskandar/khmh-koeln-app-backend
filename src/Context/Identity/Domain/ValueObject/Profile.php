<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\Enum\Language;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Profile
{
    public function __construct(
        #[ORM\Embedded(Email::class, columnPrefix: false)]
        public Email $email,
        #[ORM\Embedded(DisplayName::class, columnPrefix: false)]
        public DisplayName $displayName,
        #[ORM\Column(name: 'language', length: 2, enumType: Language::class)]
        public Language $language,
    ) {
    }

    public function withLanguage(Language $language): self
    {
        return new self($this->email, $this->displayName, $language);
    }
}
