<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Language;
use App\Context\Identity\Domain\Interface\PasswordHasherInterface;
use App\Context\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\AccountStatus;
use App\Context\Identity\Domain\ValueObject\DisplayName;
use App\Context\Identity\Domain\ValueObject\Email;
use App\Context\Identity\Domain\ValueObject\PlainPassword;
use App\Context\Identity\Domain\ValueObject\Profile;
use App\Context\Identity\Domain\ValueObject\Registration;
use App\Library\Problem\Exception\InvalidInput;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

final readonly class ApprovedAccounts
{
    public function __construct(
        private UserRepositoryInterface $users,
        private RoleRepositoryInterface $roles,
        private PasswordHasherInterface $hasher,
    ) {
    }

    public function create(
        string $email,
        string $displayName,
        Language $language,
        #[SensitiveParameter]
        ?string $password,
        ?string $role,
    ): Uuid {
        $profile = new Profile(Email::of($email), DisplayName::of($displayName), $language);

        if ($this->users->findByEmail($profile->email->value) !== null) {
            throw InvalidInput::field('email', 'accounts.emailTaken');
        }

        $user = new User(
            $profile,
            passwordHash: $this->passwordHash($password),
            status: AccountStatus::active(),
            registration: Registration::none(),
            roles: $this->rolesNamed($role),
        );

        $this->users->save($user);

        return $user->id();
    }

    /**
     * @return list<Role>
     */
    private function rolesNamed(?string $roleName): array
    {
        if ($roleName === null) {
            return [];
        }

        return [$this->roles->findByName($roleName) ?? throw InvalidInput::field('role', 'roles.unknown')];
    }

    private function passwordHash(#[SensitiveParameter] ?string $password): string
    {
        return $password === null ? $this->hasher->unusableHash() : $this->hasher->hash(PlainPassword::of($password));
    }
}
