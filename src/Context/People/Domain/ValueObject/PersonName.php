<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use App\Library\Strings\Model\NameKey;
use App\Library\Strings\Model\Text;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class PersonName
{
    private const int NAME_LENGTH = 80;

    private const int DISPLAY_NAME_LENGTH = 120;

    private function __construct(
        #[ORM\Column(name: 'first_name', length: self::NAME_LENGTH)]
        public string $firstName,
        #[ORM\Column(name: 'last_name', length: self::NAME_LENGTH)]
        public string $lastName,
        #[ORM\Column(name: 'display_name', length: self::DISPLAY_NAME_LENGTH, nullable: true)]
        public ?string $displayName,
        #[ORM\Column(name: 'name_key', type: Types::TEXT)]
        public string $key,
        #[ORM\Column(name: 'search_text', type: Types::TEXT)]
        public string $searchText,
    ) {
    }

    public static function of(string $firstName, string $lastName, ?string $displayName): self
    {
        $first = self::required('firstName', $firstName, 'people.firstNameRequired');
        $last = self::required('lastName', $lastName, 'people.lastNameRequired');

        $display = self::limited('displayName', InputText::of($displayName)->collapsed(), self::DISPLAY_NAME_LENGTH)
            ->toStringOrNull();

        $fullName = $first . ' ' . $last;

        return new self(
            firstName: $first,
            lastName: $last,
            displayName: $display,
            key: NameKey::of($fullName),
            searchText: InputText::of($fullName . ' ' . ($display ?? ''))->folded()->toString(),
        );
    }

    public function full(): string
    {
        return Text::format('%s %s', $this->firstName, $this->lastName)->toString();
    }

    private static function required(string $field, string $value, string $requiredCode): string
    {
        $clean = InputText::of($value)->collapsed();

        if ($clean->isEmpty()) {
            throw InvalidInput::field($field, $requiredCode);
        }

        return self::limited($field, $clean, self::NAME_LENGTH)->toString();
    }

    private static function limited(string $field, InputText $clean, int $max): InputText
    {
        if ($clean->length() > $max) {
            throw InvalidInput::tooLong($field, $max);
        }

        return $clean;
    }
}
