<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class PersonalDetails
{
    public function __construct(
        #[ORM\Embedded(BirthDate::class, columnPrefix: false)]
        public BirthDate $birthDate,
        #[ORM\Embedded(Address::class, columnPrefix: false)]
        public Address $address,
    ) {
    }

    public static function none(): self
    {
        return new self(BirthDate::none(), Address::none());
    }
}
