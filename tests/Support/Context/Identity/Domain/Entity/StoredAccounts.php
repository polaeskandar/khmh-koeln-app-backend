<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Identity\Domain\Entity;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Infrastructure\Persistence\Repository\DoctrineUserRepository;
use App\Context\Identity\Infrastructure\Security\SecurityUser;
use App\Tests\Support\Database;
use PHPUnit\Framework\Assert;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final readonly class StoredAccounts
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function withUsername(string $username): User
    {
        $user = new DoctrineUserRepository(new Database($this->container)->entityManager())->findByUsername($username);

        Assert::assertInstanceOf(User::class, $user);

        return $user;
    }

    public function count(): int
    {
        $count = new Database($this->container)
            ->connection()
            ->fetchOne('SELECT count(*) FROM identity.app_user');

        Assert::assertIsInt($count);

        return $count;
    }

    public function acceptsSecret(User $user, #[SensitiveParameter] string $secret): bool
    {
        $factory = $this->container->get(PasswordHasherFactoryInterface::class);
        Assert::assertInstanceOf(PasswordHasherFactoryInterface::class, $factory);

        return $factory->getPasswordHasher(SecurityUser::class)->verify($user->passwordHash(), $secret);
    }
}
