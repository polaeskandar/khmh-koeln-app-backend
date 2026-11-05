<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\EmailConfirmationToken;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\EmailConfirmationTokenRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\Email;
use App\Library\Checksum\Model\Checksum;
use App\Library\Time\Client\Calendar;
use DateInterval;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\String\ByteString;

final readonly class EmailConfirmations
{
    public const string LIFETIME = 'P7D';

    private const string TOKEN_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

    private const int TOKEN_LENGTH = 43;

    private const string VALID_UNTIL = 'd.m.Y H:i';

    public function __construct(
        private EmailConfirmationTokenRepositoryInterface $tokens,
        private AccountMailer $mailer,
        private FrontendLinks $links,
        private Calendar $calendar,
        #[Autowire(service: 'limiter.registration_email')]
        private RateLimiterFactoryInterface $mails,
    ) {
    }

    public function send(User $user): void
    {
        if (!$this->mayMail($user)) {
            return;
        }

        $token = ByteString::fromRandom(self::TOKEN_LENGTH, self::TOKEN_ALPHABET)->toString();
        $expiresAt = $this->calendar->now()->add(new DateInterval(self::LIFETIME));

        $this->tokens->replaceUnused(
            new EmailConfirmationToken($user, Checksum::sha256($token), $expiresAt->toDateTimeImmutable()),
        );

        $this->mailer->send($user, 'email_confirmation', [
            'url' => $this->links->to($user, 'confirm-email/#token=' . $token),
            'validUntil' => $expiresAt->format(self::VALID_UNTIL),
        ]);
    }

    public function remind(User $existing): void
    {
        if (!$existing->status()->isEmailConfirmed()) {
            $this->send($existing);

            return;
        }

        if ($this->mayMail($existing)) {
            $this->mailer->send($existing, 'account_exists', ['url' => $this->links->to($existing, 'sign-in')]);
        }
    }

    public function confirm(#[SensitiveParameter] string $token): bool
    {
        $now = $this->calendar->now()->toDateTimeImmutable();

        return $this->tokens->spend(
            Checksum::sha256($token),
            $now,
            static fn (User $user) => $user->changeStatus($user->status()->emailConfirmedOn($now)),
        );
    }

    private function mayMail(User $user): bool
    {
        return $this->mails->create(Email::normalized($user->profile()->email->value))->consume()->isAccepted();
    }
}
