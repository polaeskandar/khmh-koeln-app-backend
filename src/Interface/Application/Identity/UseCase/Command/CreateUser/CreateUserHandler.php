<?php

declare(strict_types=1);

namespace App\Interface\Application\Identity\UseCase\Command\CreateUser;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\Service\PasswordHasherInterface;
use App\Context\Identity\Domain\ValueObject\DisplayName;
use App\Context\Identity\Domain\ValueObject\Email;
use App\Context\Identity\Domain\ValueObject\PlainPassword;
use App\Context\Identity\Domain\ValueObject\Profile;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Time\Client\Calendar;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

final readonly class CreateUserHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordHasherInterface $hasher,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(CreateUserCommand $command): Uuid
    {
        $email = Email::of($command->email);
        $displayName = DisplayName::of($command->displayName);

        if ($this->users->findByEmail($email->value) !== null) {
            throw InvalidInput::field('email', 'accounts.emailTaken');
        }

        $user = User::approved(
            new Profile($email, $displayName, $command->language),
            passwordHash: $this->passwordHash($command->password),
            approvedAt: $this->calendar->now(),
        );

        $this->users->save($user);

        return $user->id();
    }

    private function passwordHash(#[SensitiveParameter] ?string $password): string
    {
        return $password === null ? $this->hasher->unusableHash() : $this->hasher->hash(PlainPassword::of($password));
    }
}
