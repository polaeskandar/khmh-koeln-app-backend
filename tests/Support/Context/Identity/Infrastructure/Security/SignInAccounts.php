<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Infrastructure\Security\SecurityUser;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Database;
use PHPUnit\Framework\Assert;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class SignInAccounts
{
    public const string TYPED = 'correct horse battery';

    public const string MISTYPED = 'correct horse staple';

    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * @param array<string, string|null> $columns
     */
    public function add(string $email, array $columns = [], #[SensitiveParameter] string $secret = self::TYPED): string
    {
        $factory = $this->container->get(PasswordHasherFactoryInterface::class);
        Assert::assertInstanceOf(PasswordHasherFactoryInterface::class, $factory);
        $id = Uuid::v7()->toRfc4122();
        $connection = new Database($this->container)->connection();

        $connection->insert(
            'identity.app_user',
            Accounts::row(Accounts::named($connection, [
                'id' => $id,
                'email' => $email,
                'password_hash' => $factory->getPasswordHasher(SecurityUser::class)->hash($secret),
                ...$columns,
            ])),
        );

        return $id;
    }

    /**
     * @param array<string, string|null> $columns
     */
    public function addWithPerson(string $email, array $columns = []): string
    {
        return $this->add($email, ['display_name' => 'Sara Hanna', ...$columns]);
    }
}
