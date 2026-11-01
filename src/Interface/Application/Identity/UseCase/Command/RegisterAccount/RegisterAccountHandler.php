<?php

declare(strict_types=1);

namespace App\Interface\Application\Identity\UseCase\Command\RegisterAccount;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Language;
use App\Context\Identity\Domain\Enum\RegisteredAs;
use App\Context\Identity\Domain\Service\PasswordHasherInterface;
use App\Context\Identity\Domain\ValueObject\DisplayName;
use App\Context\Identity\Domain\ValueObject\Email;
use App\Context\Identity\Domain\ValueObject\PlainPassword;
use App\Context\Identity\Domain\ValueObject\Profile;
use App\Context\Identity\Domain\ValueObject\Registration;
use App\Interface\Application\Identity\Input\AccountInput;
use App\Interface\Application\Identity\Service\ChosenServices;
use App\Interface\Application\Identity\Service\RegistrationRecords;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Strings\Model\InputText;
use App\Library\Strings\Model\Text;
use App\Library\Time\Client\Calendar;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class RegisterAccountHandler
{
    public function __construct(
        private RegistrationRecords $records,
        private ChosenServices $chosenServices,
        private PasswordHasherInterface $hasher,
        private Calendar $calendar,
        #[Autowire(param: 'app.privacy_notice_version')]
        private string $privacyNoticeVersion,
    ) {
    }

    public function __invoke(RegisterAccountCommand $command): void
    {
        if ($command->privacy->version !== $this->privacyNoticeVersion) {
            throw new RuleViolation('accounts.privacyNoticeOutdated');
        }

        $passwordHash = $this->hasher->hash(PlainPassword::of($command->account->password ?? ''));
        $services = $this->chosenServices->of($command->registration);

        $registration = Registration::of(
            RegisteredAs::from($command->registration->registeredAs ?? ''),
            InputText::of($command->registration->note)->trimmed()->toString(),
            privacyAcceptedAt: $this->calendar->now(),
            privacyVersion: $this->privacyNoticeVersion,
        );

        $this->records->storeUnlessTaken(
            User::registered(self::profileOf($command->account), $passwordHash, $registration),
            $services,
        );
    }

    private static function profileOf(AccountInput $account): Profile
    {
        $name = Text::format('%s %s', $account->firstName ?? '', $account->lastName ?? '')->toString();

        return new Profile(
            Email::of($account->email ?? ''),
            DisplayName::of(InputText::of($name)->collapsed()->toString()),
            Language::from($account->language ?? ''),
        );
    }
}
