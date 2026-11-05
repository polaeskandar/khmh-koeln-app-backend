<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Enum\Language;
use App\Context\Identity\Domain\Enum\RegisteredAs;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use LogicException;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

final readonly class DemoAccounts
{
    private const array APPROVED = [
        ['mariam@example.org',    'Mariam Fahmy',   'administrator'],
        ['christine@example.org', 'Christine Adel', 'service_leader'],
        ['daniel@example.org',    'Daniel Michael', 'servant'],
        ['hoda@example.org',      'Hoda Farag',     'parent'],
    ];

    public function __construct(
        private ApprovedAccounts $approved,
        private AccountRegistration $registration,
        private UserRepositoryInterface $users,
        #[Autowire(param: 'app.privacy_notice_version')]
        private string $privacyNoticeVersion,
        #[Autowire(env: 'DEMO_PASSWORD')]
        #[SensitiveParameter]
        private string $password,
    ) {
    }

    public function seed(): void
    {
        foreach (self::APPROVED as [$email, $name, $role]) {
            $this->approved->create($email, $name, Language::German->value, $this->password, $role);
        }

        $nadia = $this->registration->registerUnlessTaken(
            $this->registration->profile('Nadia', 'Mansour', 'nadia@example.org', Language::German->value),
            $this->password,
            RegisteredAs::Parent,
            note: 'Mother of Sophia Mansour, Kindergarten',
            privacyVersion: $this->privacyNoticeVersion,
        );

        if ($nadia !== null) {
            $this->registration->markEmailConfirmed($nadia);
        }
    }

    public function idOf(string $email): Uuid
    {
        $account = $this->users->findByEmail($email) ?? throw new LogicException('The demo has no ' . $email . '.');

        return $account->id();
    }
}
