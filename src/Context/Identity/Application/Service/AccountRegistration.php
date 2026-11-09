<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Enum\Language;
use App\Context\Identity\Domain\Enum\RegisteredAs;
use App\Context\Identity\Domain\Interface\PasswordHasherInterface;
use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
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
        private AccountRepositoryInterface $accounts,
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

        $existing = $this->accounts->findByEmail($profile->email->value);

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

        $account = new Account($profile, $passwordHash, AccountStatus::pending(), $registration, roles: []);
        $this->accounts->save($account);

        return $account->id();
    }

    public function sendConfirmation(?Uuid $registered): void
    {
        if ($registered === null) {
            return;
        }

        $this->confirmations->send($this->account($registered));
    }

    public function markEmailConfirmed(Uuid $accountId): void
    {
        $account = $this->account($accountId);
        $account->changeStatus($account->status()->emailConfirmedOn($account->createdAt()));
        $this->accounts->save($account);
    }

    private function account(Uuid $accountId): Account
    {
        return $this->accounts->find($accountId) ?? throw NotFound::of('user');
    }
}
