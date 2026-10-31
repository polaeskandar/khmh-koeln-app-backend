<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Entity;

use App\Context\Identity\Domain\ValueObject\AccountStatus;
use App\Context\Identity\Domain\ValueObject\PersonalDetails;
use App\Context\Identity\Domain\ValueObject\Profile;
use App\Context\Identity\Domain\ValueObject\Registration;
use App\Library\Persistence\Mapping\CreatedAtEntity;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'app_user', schema: 'identity')]
#[ORM\UniqueConstraint(name: 'app_user_email_unique', columns: ['email'])]
final class User extends CreatedAtEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private readonly Uuid $id;

    #[ORM\Embedded(Profile::class, columnPrefix: false)]
    private Profile $profile;

    #[ORM\Column(name: 'password_hash', length: 255)]
    private string $passwordHash;

    #[ORM\Embedded(Registration::class, columnPrefix: false)]
    private Registration $registration;

    #[ORM\Embedded(PersonalDetails::class, columnPrefix: false)]
    private PersonalDetails $personalDetails;

    #[ORM\Embedded(AccountStatus::class, columnPrefix: false)]
    private AccountStatus $status;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'approved_by', nullable: true, onDelete: 'SET NULL')]
    private ?self $approvedBy = null;

    private function __construct(Profile $profile, string $passwordHash, AccountStatus $status)
    {
        $this->id = Uuid::v7();
        $this->profile = $profile;
        $this->passwordHash = $passwordHash;
        $this->registration = Registration::none();
        $this->personalDetails = PersonalDetails::none();
        $this->status = $status;
    }

    public static function approved(Profile $profile, string $passwordHash, DateTimeImmutable $approvedAt): self
    {
        return new self($profile, $passwordHash, AccountStatus::approvedAt($approvedAt));
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function profile(): Profile
    {
        return $this->profile;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function registration(): Registration
    {
        return $this->registration;
    }

    public function personalDetails(): PersonalDetails
    {
        return $this->personalDetails;
    }

    public function status(): AccountStatus
    {
        return $this->status;
    }

    public function approvedBy(): ?self
    {
        return $this->approvedBy;
    }
}
