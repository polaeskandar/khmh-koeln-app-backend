<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Entity;

use App\Context\Identity\Domain\ValueObject\AccountStatus;
use App\Context\Identity\Domain\ValueObject\MemberLink;
use App\Context\Identity\Domain\ValueObject\PersonalDetails;
use App\Context\Identity\Domain\ValueObject\Profile;
use App\Context\Identity\Domain\ValueObject\Registration;
use App\Library\Persistence\Mapping\CreatedAtEntity;
use App\Library\Problem\Exception\RuleViolation;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'app_user', schema: 'identity')]
#[ORM\UniqueConstraint(name: 'app_user_email_unique', columns: ['email'])]
#[ORM\UniqueConstraint(
    name: 'app_user_person_unique',
    columns: ['person_id'],
    options: ['where' => '(person_id IS NOT NULL)'],
)]
final class User extends CreatedAtEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

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

    #[ORM\Embedded(MemberLink::class, columnPrefix: false)]
    private MemberLink $member;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'approved_by', nullable: true, onDelete: 'SET NULL')]
    private ?self $approvedBy = null;

    /**
     * @var Collection<int, Role>
     */
    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinTable(name: 'user_role', schema: 'identity')]
    #[ORM\JoinColumn(name: 'user_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'role_id', onDelete: 'CASCADE')]
    private Collection $roles;

    private function __construct(Profile $profile, string $passwordHash, AccountStatus $status)
    {
        $this->id = Uuid::v7();
        $this->profile = $profile;
        $this->passwordHash = $passwordHash;
        $this->registration = Registration::none();
        $this->personalDetails = PersonalDetails::none();
        $this->status = $status;
        $this->member = MemberLink::none();
        $this->roles = new ArrayCollection();
    }

    /**
     * @param list<Role> $roles
     */
    public static function approved(
        Profile $profile,
        string $passwordHash,
        DateTimeImmutable $approvedAt,
        array $roles = [],
    ): self {
        $user = new self($profile, $passwordHash, AccountStatus::pending()->approvedOn($approvedAt));
        $user->roles = new ArrayCollection($roles);

        return $user;
    }

    public static function registered(Profile $profile, string $passwordHash, Registration $registration): self
    {
        $user = new self($profile, $passwordHash, AccountStatus::pending());
        $user->registration = $registration;

        return $user;
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

    public function changeApproval(?self $by, AccountStatus $status, Registration $registration): void
    {
        $this->status = $status;
        $this->approvedBy = $by;
        $this->registration = $registration;
    }

    public function changePassword(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function changeProfile(Profile $profile): void
    {
        $this->profile = $profile;
    }

    public function changeStatus(AccountStatus $status): void
    {
        $this->status = $status;
    }

    public function member(): MemberLink
    {
        return $this->member;
    }

    public function changeMember(MemberLink $member): void
    {
        $this->member = $member;
    }

    public function approvedBy(): ?self
    {
        return $this->approvedBy;
    }

    /**
     * @return ReadableCollection<int, Role>
     */
    public function roles(): ReadableCollection
    {
        return $this->roles;
    }

    public function assign(Role $role): void
    {
        if ($this->roles->contains($role)) {
            throw new RuleViolation('roles.alreadyHasRole', ['name' => $this->profile->displayName->value]);
        }

        $this->roles->add($role);
    }

    public function unassign(Role $role): void
    {
        if (!$this->roles->removeElement($role)) {
            throw new RuleViolation('roles.notHeld', ['name' => $this->profile->displayName->value]);
        }
    }
}
