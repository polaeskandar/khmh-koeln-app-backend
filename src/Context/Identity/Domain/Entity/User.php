<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Entity;

use App\Context\Identity\Domain\Enum\Language;
use App\Context\Identity\Domain\ValueObject\AccountStatus;
use App\Context\Identity\Domain\ValueObject\PersonLink;
use App\Context\Identity\Domain\ValueObject\Registration;
use App\Context\Identity\Domain\ValueObject\Username;
use App\Library\Persistence\Mapping\CreatedAtEntity;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'app_user', schema: 'identity')]
#[ORM\UniqueConstraint(name: 'app_user_username_unique', columns: ['username'])]
#[ORM\UniqueConstraint(name: 'app_user_person_unique', columns: ['person_id'])]
final class User extends CreatedAtEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Embedded(Username::class, columnPrefix: false)]
    private Username $username;

    #[ORM\Column(name: 'language', length: 2, enumType: Language::class)]
    private Language $language;

    #[ORM\Column(name: 'password_hash', length: 255)]
    private string $passwordHash;

    #[ORM\Embedded(Registration::class, columnPrefix: false)]
    private Registration $registration;

    #[ORM\Embedded(AccountStatus::class, columnPrefix: false)]
    private AccountStatus $status;

    #[ORM\Embedded(PersonLink::class, columnPrefix: false)]
    private PersonLink $person;

    #[ORM\Column(name: 'erases_on', type: 'local_date', nullable: true)]
    private ?LocalDate $erasesOn = null;

    /**
     * @var Collection<int, Role>
     */
    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinTable(name: 'user_role', schema: 'identity')]
    #[ORM\JoinColumn(name: 'user_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'role_id', onDelete: 'CASCADE')]
    private Collection $roles;

    /**
     * @param list<Role> $roles
     */
    public function __construct(
        Uuid $personId,
        Username $username,
        Language $language,
        string $passwordHash,
        AccountStatus $status,
        Registration $registration,
        array $roles,
    ) {
        $this->id = Uuid::v7();
        $this->person = PersonLink::to($personId);
        $this->username = $username;
        $this->language = $language;
        $this->passwordHash = $passwordHash;
        $this->registration = $registration;
        $this->status = $status;
        $this->roles = new ArrayCollection($roles);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function username(): Username
    {
        return $this->username;
    }

    public function language(): Language
    {
        return $this->language;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function registration(): Registration
    {
        return $this->registration;
    }

    public function status(): AccountStatus
    {
        return $this->status;
    }

    public function changePassword(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function changeLanguage(Language $language): void
    {
        $this->language = $language;
    }

    public function changeStatus(AccountStatus $status): void
    {
        $this->status = $status;
    }

    public function personId(): Uuid
    {
        return $this->person->personId;
    }

    public function person(): PersonLink
    {
        return $this->person;
    }

    public function erasesOn(): ?LocalDate
    {
        return $this->erasesOn;
    }

    public function changeErasure(?LocalDate $erasesOn): void
    {
        $this->erasesOn = $erasesOn;
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
        $this->roles->add($role);
    }

    public function unassign(Role $role): void
    {
        $this->roles->removeElement($role);
    }
}
