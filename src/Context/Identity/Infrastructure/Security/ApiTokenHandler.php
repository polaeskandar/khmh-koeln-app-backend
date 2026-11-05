<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Domain\Repository\ApiTokenRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Library\Checksum\Model\Checksum;
use Override;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final readonly class ApiTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private ApiTokenRepositoryInterface $tokens,
        private UserRepositoryInterface $users,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function getUserBadgeFrom(#[SensitiveParameter] string $accessToken): UserBadge
    {
        $holder = $this->tokens->holderOf(Checksum::sha256($accessToken), $this->clock->now());
        $user = $holder === null ? null : $this->users->find($holder);

        if ($user === null || !$user->status()->isActive()) {
            throw new InvalidApiToken();
        }

        $securityUser = SecurityUser::of($user);

        return new UserBadge($securityUser->getUserIdentifier(), static fn (): SecurityUser => $securityUser);
    }
}
