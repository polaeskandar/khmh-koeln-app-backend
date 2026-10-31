<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Address
{
    private const int PARTS = 3;

    private function __construct(
        #[ORM\Column(name: 'street', length: 120, nullable: true)]
        public ?string $street,
        #[ORM\Column(name: 'postal_code', length: 10, nullable: true)]
        public ?string $postalCode,
        #[ORM\Column(name: 'city', length: 80, nullable: true)]
        public ?string $city,
    ) {
    }

    public static function none(): self
    {
        return new self(street: null, postalCode: null, city: null);
    }

    public static function of(?string $street, ?string $postalCode, ?string $city): self
    {
        $address = new self(
            street: self::part('street', $street, max: 120),
            postalCode: self::part('postalCode', $postalCode, max: 10),
            city: self::part('city', $city, max: 80),
        );

        $given = new ArrayCollection([$address->street, $address->postalCode, $address->city])
            ->filter(static fn (?string $part): bool => $part !== null)
            ->count();

        if ($given !== 0 && $given !== self::PARTS) {
            throw InvalidInput::field('address', 'validation.addressIncomplete');
        }

        return $address;
    }

    private static function part(string $field, ?string $value, int $max): ?string
    {
        $clean = InputText::of($value)->collapsed();

        if ($clean->length() > $max) {
            throw InvalidInput::tooLong($field, $max);
        }

        return $clean->toStringOrNull();
    }
}
