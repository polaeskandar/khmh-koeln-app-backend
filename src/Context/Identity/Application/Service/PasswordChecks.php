<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Interface\CredentialCheckInterface;
use App\Context\Identity\Domain\Interface\PasswordHasherInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Time\Client\Calendar;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class PasswordChecks
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordHasherInterface $hasher,
        private CredentialCheckInterface $credentials,
        #[Autowire(service: 'limiter.password_change')]
        private RateLimiterFactoryInterface $attempts,
        private Calendar $calendar,
    ) {
    }

    public function ensureOwn(Uuid $accountId, #[SensitiveParameter] string $password, string $field): void
    {
        $this->ensureAttemptsLeft($accountId);
        $hash = $this->users->find($accountId)?->passwordHash();

        if ($hash === null || !$this->hasher->matches($hash, $password)) {
            $this->refuseWrongPassword($accountId, $field);
        }
    }

    public function ensureAttemptsLeft(Uuid $accountId): void
    {
        $left = $this->attemptsOf($accountId)->consume(0);

        if ($left->getRemainingTokens() > 0) {
            return;
        }

        $seconds = $left->getRetryAfter()->getTimestamp() - $this->calendar->now()->getTimestamp();

        throw new TooManyRequestsHttpException($seconds, 'auth.tooManyAttempts');
    }

    public function ensureMatches(User $user, #[SensitiveParameter] string $password, string $field): void
    {
        if ($this->hasher->matches($user->passwordHash(), $password)) {
            return;
        }

        $this->refuseWrongPassword($user->id(), $field);
    }

    public function accountWith(string $email, #[SensitiveParameter] string $password): ?Uuid
    {
        return $this->credentials->accountWith($email, $password);
    }

    private function refuseWrongPassword(Uuid $accountId, string $field): never
    {
        $this->attemptsOf($accountId)->consume();

        throw InvalidInput::field($field, 'accounts.currentPasswordWrong');
    }

    private function attemptsOf(Uuid $accountId): LimiterInterface
    {
        return $this->attempts->create($accountId->toRfc4122());
    }
}
