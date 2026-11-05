<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\PasswordToken;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\PasswordTokenRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Library\Checksum\Model\Checksum;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Client\Calendar;
use DateInterval;
use DateTimeImmutable;
use Symfony\Component\String\ByteString;

final readonly class PasswordLinks
{
    private const string TOKEN_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

    private const int TOKEN_LENGTH = 43;

    private const string LIFETIME = 'PT24H';

    private const string RESET_LIFETIME = 'PT1H';

    private const string VALID_UNTIL = 'd.m.Y H:i';

    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordTokenRepositoryInterface $tokens,
        private AccountMailer $mailer,
        private Calendar $calendar,
        private FrontendLinks $links,
    ) {
    }

    public function sendTo(string $email): DateTimeImmutable
    {
        $user = $this->users->findByEmail($email) ?? throw NotFound::of('account');

        if (!$user->status()->isActive()) {
            throw new RuleViolation('accounts.passwordLinkUnavailable');
        }

        return $this->issue($user, self::LIFETIME);
    }

    public function resetFor(string $email): void
    {
        $user = $this->users->findByEmail($email);

        if ($user?->status()->isActive() === true) {
            $this->issue($user, self::RESET_LIFETIME);
        }
    }

    private function issue(User $user, string $lifetime): DateTimeImmutable
    {
        $token = ByteString::fromRandom(self::TOKEN_LENGTH, self::TOKEN_ALPHABET)->toString();
        $expiresAt = $this->calendar->now()->add(new DateInterval($lifetime));
        $this->tokens->replaceUnused(new PasswordToken($user, Checksum::sha256($token), $expiresAt, createdBy: null));

        $this->mailer->send($user, 'password_link', [
            'url' => $this->links->to($user, 'set-password/#token=' . $token),
            'validUntil' => $expiresAt->format(self::VALID_UNTIL),
        ]);

        return $expiresAt;
    }
}
