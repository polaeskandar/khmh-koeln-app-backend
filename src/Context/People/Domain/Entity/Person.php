<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Entity;

use App\Context\People\Domain\Enum\PersonStatus;
use App\Context\People\Domain\ValueObject\PersonalDetails;
use App\Context\People\Domain\ValueObject\PersonName;
use App\Library\Persistence\Mapping\CreatedAtEntity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'person', schema: 'people')]
#[ORM\Index(name: 'person_name_key_idx', columns: ['name_key'])]
final class Person extends CreatedAtEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Embedded(PersonName::class, columnPrefix: false)]
    private PersonName $name;

    #[ORM\Column(length: 8, enumType: PersonStatus::class)]
    private PersonStatus $status;

    #[ORM\Embedded(PersonalDetails::class, columnPrefix: false)]
    private PersonalDetails $personalDetails;

    public function __construct(PersonName $name)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->status = PersonStatus::Active;
        $this->personalDetails = PersonalDetails::none();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): PersonName
    {
        return $this->name;
    }

    public function rename(PersonName $name): void
    {
        $this->name = $name;
    }

    public function status(): PersonStatus
    {
        return $this->status;
    }

    public function changeStatus(PersonStatus $status): void
    {
        $this->status = $status;
    }

    public function personalDetails(): PersonalDetails
    {
        return $this->personalDetails;
    }

    public function recordPersonalDetails(PersonalDetails $personalDetails): void
    {
        $this->personalDetails = $personalDetails;
    }
}
