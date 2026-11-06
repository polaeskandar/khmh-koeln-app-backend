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
use App\Library\Strings\Model\Text;
use App\Library\Time\Client\Calendar;
use DateTimeImmutable;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\ByteString;

final readonly class PasswordLinks
{
    private const string TOKEN_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

    private const int TOKEN_LENGTH = 43;

    private const int LIFETIME_HOURS = 24;

    private const string VALID_UNTIL = 'd.m.Y H:i';

    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordTokenRepositoryInterface $tokens,
        private AccountMailer $mailer,
        private Calendar $calendar,
        #[Autowire(env: 'FRONTEND_URL')]
        private string $frontendUrl,
    ) {
    }

    public function sendTo(string $email): DateTimeImmutable
    {
        $user = $this->users->findByEmail($email) ?? throw NotFound::of('account');

        if (!$user->status()->isActive()) {
            throw new RuleViolation('accounts.passwordLinkUnavailable');
        }

        $token = ByteString::fromRandom(self::TOKEN_LENGTH, self::TOKEN_ALPHABET)->toString();
        $expiresAt = $this->calendar->now()->addHours(self::LIFETIME_HOURS);

        $this->tokens->replaceUnused(new PasswordToken($user, Checksum::sha256($token), $expiresAt, createdBy: null));

        $this->mailer->send($user, template: 'password_link', params: [
            'url' => $this->urlFor($user, $token),
            'validUntil' => $expiresAt->format(self::VALID_UNTIL),
        ]);

        return $expiresAt;
    }

    private function urlFor(User $user, #[SensitiveParameter] string $token): string
    {
        return Text::format(
            '%s/%s/set-password/#token=%s',
            Text::of($this->frontendUrl)->replaceMatches('#/+$#', replacement: '')->toString(),
            $user->profile()->language->value,
            $token,
        )->toString();
    }
}
