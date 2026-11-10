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
use DateTimeImmutable;
use Symfony\Component\String\ByteString;
use Symfony\Component\Uid\Uuid;

final readonly class PasswordLinks
{
    private const string TOKEN_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

    private const int TOKEN_LENGTH = 43;

    private const int LIFETIME_HOURS = 24;

    private const int RESET_LIFETIME_HOURS = 1;

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

        return $this->issue($this->active($user), self::LIFETIME_HOURS, createdBy: null);
    }

    public function sendToAccount(Uuid $accountId, Uuid $actorId): DateTimeImmutable
    {
        $user = $this->account($accountId);

        return $this->issue($this->active($user), self::LIFETIME_HOURS, createdBy: $this->account($actorId));
    }

    public function resetFor(string $email): void
    {
        $user = $this->users->findByEmail($email);

        if ($user?->status()->isActive() === true) {
            $this->issue($user, self::RESET_LIFETIME_HOURS, createdBy: null);
        }
    }

    private function active(User $user): User
    {
        return $user->status()->isActive() ? $user : throw new RuleViolation('accounts.passwordLinkUnavailable');
    }

    private function account(Uuid $id): User
    {
        return $this->users->find($id) ?? throw NotFound::of('user');
    }

    private function issue(User $user, int $hours, ?User $createdBy): DateTimeImmutable
    {
        $token = ByteString::fromRandom(self::TOKEN_LENGTH, self::TOKEN_ALPHABET)->toString();
        $expiresAt = $this->calendar->now()->addHours($hours);

        $this->tokens->replaceUnused(new PasswordToken($user, Checksum::sha256($token), $expiresAt, $createdBy));

        $this->mailer->send($user, template: 'password_link', params: [
            'url' => $this->links->to($user, 'set-password/#token=' . $token),
            'validUntil' => $expiresAt->format(self::VALID_UNTIL),
        ]);

        return $expiresAt;
    }
}
