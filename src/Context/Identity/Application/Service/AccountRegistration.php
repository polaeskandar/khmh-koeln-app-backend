<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Language;
use App\Context\Identity\Domain\Enum\RegisteredAs;
use App\Context\Identity\Domain\Interface\PasswordHasherInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\AccountStatus;
use App\Context\Identity\Domain\ValueObject\DisplayName;
use App\Context\Identity\Domain\ValueObject\Email;
use App\Context\Identity\Domain\ValueObject\PlainPassword;
use App\Context\Identity\Domain\ValueObject\Profile;
use App\Context\Identity\Domain\ValueObject\Registration;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Strings\Model\InputText;
use App\Library\Strings\Model\Text;
use App\Library\Time\Client\Calendar;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

final readonly class AccountRegistration
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordHasherInterface $hasher,
        private Calendar $calendar,
        private EmailConfirmations $confirmations,
        #[Autowire(param: 'app.privacy_notice_version')]
        private string $privacyNoticeVersion,
    ) {
    }

    public function profile(string $firstName, string $lastName, string $email, string $language): Profile
    {
        $name = Text::format('%s %s', $firstName, $lastName)->toString();

        return new Profile(
            Email::of($email),
            DisplayName::of(InputText::of($name)->collapsed()->toString()),
            Language::from($language),
        );
    }

    public function registerUnlessTaken(
        Profile $profile,
        #[SensitiveParameter]
        string $password,
        RegisteredAs $registeredAs,
        string $note,
        string $privacyVersion,
    ): ?Uuid {
        if ($privacyVersion !== $this->privacyNoticeVersion) {
            throw new RuleViolation('accounts.privacyNoticeOutdated');
        }

        $passwordHash = $this->hasher->hash(PlainPassword::of($password));

        $existing = $this->users->findByEmail($profile->email->value);

        if ($existing !== null) {
            $this->confirmations->remind($existing);

            return null;
        }

        $registration = Registration::of(
            $registeredAs,
            InputText::of($note)->trimmed()->toString(),
            privacyAcceptedAt: $this->calendar->now(),
            privacyVersion: $this->privacyNoticeVersion,
        );

        $user = new User($profile, $passwordHash, AccountStatus::pending(), $registration, roles: []);
        $this->users->save($user);

        return $user->id();
    }

    public function sendConfirmation(?Uuid $registered): void
    {
        if ($registered === null) {
            return;
        }

        $this->confirmations->send($this->account($registered));
    }

    public function confirmEmailByHand(string $email): bool
    {
        return $this->confirmations->confirmByHand($this->users->findByEmail($email) ?? throw NotFound::of('account'));
    }

    public function markEmailConfirmed(Uuid $accountId): void
    {
        $user = $this->account($accountId);
        $user->changeStatus($user->status()->emailConfirmedOn($user->createdAt()));
        $this->users->save($user);
    }

    private function account(Uuid $accountId): User
    {
        return $this->users->find($accountId) ?? throw NotFound::of('user');
    }
}
