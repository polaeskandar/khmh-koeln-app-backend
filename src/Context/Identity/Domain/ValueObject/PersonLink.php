<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\Enum\AgeCheck;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Embeddable]
final readonly class PersonLink
{
    public const int NOTE_LENGTH = 200;

    private function __construct(
        #[ORM\Column(name: 'person_id', type: UuidType::NAME)]
        public Uuid $personId,
        #[ORM\Column(name: 'age_check', length: 15, nullable: true, enumType: AgeCheck::class)]
        public ?AgeCheck $ageCheck,
        #[ORM\Column(name: 'guardian_consent_note', length: self::NOTE_LENGTH, nullable: true)]
        public ?string $guardianConsentNote,
    ) {
    }

    public static function to(Uuid $personId): self
    {
        return new self($personId, ageCheck: null, guardianConsentNote: null);
    }

    public function asMember(AgeCheck $ageCheck, ?string $note): self
    {
        $consent = $ageCheck === AgeCheck::GuardianConsent ? InputText::of($note)->trimmed() : InputText::of(null);

        if ($consent->length() > self::NOTE_LENGTH) {
            throw InvalidInput::tooLong('guardianConsentNote', self::NOTE_LENGTH);
        }

        return new self($this->personId, $ageCheck, $consent->toStringOrNull());
    }

    public function isMember(): bool
    {
        return $this->ageCheck !== null;
    }
}
