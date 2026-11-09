<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Identity\Domain\Entity;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Infrastructure\Persistence\Repository\DoctrineAccountRepository;
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

    public function withEmail(string $email): Account
    {
        $account = new DoctrineAccountRepository(new Database($this->container)->entityManager())->findByEmail($email);
        Assert::assertInstanceOf(Account::class, $account);

        return $account;
    }

    public function count(): int
    {
        $count = new Database($this->container)
            ->connection()
            ->fetchOne('SELECT count(*) FROM identity.app_user');

        Assert::assertIsInt($count);

        return $count;
    }

    public function acceptsSecret(Account $account, #[SensitiveParameter] string $secret): bool
    {
        $factory = $this->container->get(PasswordHasherFactoryInterface::class);
        Assert::assertInstanceOf(PasswordHasherFactoryInterface::class, $factory);

        return $factory->getPasswordHasher(SecurityUser::class)->verify($account->passwordHash(), $secret);
    }
}
