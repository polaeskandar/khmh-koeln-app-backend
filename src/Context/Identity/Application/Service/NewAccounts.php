<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Interface\PasswordHasherInterface;
use App\Context\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\AccountDraft;
use App\Context\Identity\Domain\ValueObject\AccountStatus;
use App\Context\Identity\Domain\ValueObject\DisplayName;
use App\Context\Identity\Domain\ValueObject\Email;
use App\Context\Identity\Domain\ValueObject\PlainPassword;
use App\Context\Identity\Domain\ValueObject\Profile;
use App\Context\Identity\Domain\ValueObject\Registration;
use App\Context\Identity\Domain\ValueObject\Username;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\RuleViolation;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

final readonly class NewAccounts
{
    public function __construct(
        private UserRepositoryInterface $users,
        private RoleRepositoryInterface $roles,
        private PasswordHasherInterface $hasher,
    ) {
    }

    public function create(AccountDraft $draft, #[SensitiveParameter] string $password, ?string $role): Uuid
    {
        $name = Username::of($draft->username);
        $profile = new Profile(Email::of($draft->email), DisplayName::of($draft->displayName), $draft->language);

        if ($this->users->findByUsername($name->value) !== null) {
            throw new RuleViolation('accounts.usernameTaken');
        }

        if ($this->users->findByEmail($profile->email->value) !== null) {
            throw InvalidInput::field('email', 'accounts.emailTaken');
        }

        $user = new User(
            $name,
            $profile,
            passwordHash: $this->hasher->hash(PlainPassword::of($password)),
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
}
