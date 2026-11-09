<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
use Override;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\String\ByteString;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @implements UserProviderInterface<SecurityUser>
 */
final readonly class AccountProvider implements UserProviderInterface
{
    private const string DUMMY_HASH_KEY = 'identity.dummy_password_hash';

    private const int DUMMY_SECRET_LENGTH = 32;

    public function __construct(
        private AccountRepositoryInterface $accounts,
        private PasswordHasherFactoryInterface $hashers,
        private CacheInterface $cache,
    ) {
    }

    #[Override]
    public function loadUserByIdentifier(string $identifier): SecurityUser
    {
        $account = $this->accounts->findByEmail($identifier);

        if ($account === null) {
            $this->spendAPasswordCheck();

            throw new UserNotFoundException();
        }

        return SecurityUser::of($account);
    }

    #[Override]
    public function refreshUser(UserInterface $user): SecurityUser
    {
        $account = $this->accounts->find(Uuid::fromString($user->getUserIdentifier()));

        return $account === null ? throw new UserNotFoundException() : SecurityUser::of($account);
    }

    #[Override]
    public function supportsClass(string $class): bool
    {
        return $class === SecurityUser::class;
    }

    private function spendAPasswordCheck(): void
    {
        $hasher = $this->hashers->getPasswordHasher(SecurityUser::class);

        $dummyHash = $this->cache->get(self::DUMMY_HASH_KEY, static fn (): string => $hasher->hash(
            ByteString::fromRandom(self::DUMMY_SECRET_LENGTH)->toString(),
        ));

        $hasher->verify($dummyHash, plainPassword: '');
    }
}
