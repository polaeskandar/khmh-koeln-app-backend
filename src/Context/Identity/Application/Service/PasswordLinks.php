<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Entity\PasswordToken;
use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
use App\Context\Identity\Domain\Repository\PasswordTokenRepositoryInterface;
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
        private AccountRepositoryInterface $accounts,
        private PasswordTokenRepositoryInterface $tokens,
        private AccountMailer $mailer,
        private Calendar $calendar,
        private FrontendLinks $links,
    ) {
    }

    public function sendTo(string $email): DateTimeImmutable
    {
        $account = $this->accounts->findByEmail($email) ?? throw NotFound::of('account');

        return $this->issue($this->active($account), self::LIFETIME_HOURS, createdBy: null);
    }

    public function sendToAccount(string $accountId, string $actorId): DateTimeImmutable
    {
        $account = $this->account($accountId);

        return $this->issue($this->active($account), self::LIFETIME_HOURS, createdBy: $this->account($actorId));
    }

    public function resetFor(string $email): void
    {
        $account = $this->accounts->findByEmail($email);

        if ($account?->status()->isActive() === true) {
            $this->issue($account, self::RESET_LIFETIME_HOURS, createdBy: null);
        }
    }

    private function active(Account $account): Account
    {
        return $account->status()->isActive() ? $account : throw new RuleViolation('accounts.passwordLinkUnavailable');
    }

    private function account(string $id): Account
    {
        $account = Uuid::isValid($id) ? $this->accounts->find(Uuid::fromString($id)) : null;

        return $account ?? throw NotFound::of('user');
    }

    private function issue(Account $account, int $hours, ?Account $createdBy): DateTimeImmutable
    {
        $token = ByteString::fromRandom(self::TOKEN_LENGTH, self::TOKEN_ALPHABET)->toString();
        $expiresAt = $this->calendar->now()->addHours($hours);

        $this->tokens->replaceUnused(new PasswordToken($account, Checksum::sha256($token), $expiresAt, $createdBy));

        $this->mailer->send($account, template: 'password_link', params: [
            'url' => $this->links->to($account, 'set-password/#token=' . $token),
            'validUntil' => $expiresAt->format(self::VALID_UNTIL),
        ]);

        return $expiresAt;
    }
}
