<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Identity\Application\Service;

use App\Context\Identity\Application\Service\LastAdministrator;
use App\Context\Identity\Application\Service\RoleAssignments;
use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Database;
use PHPUnit\Framework\Assert;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class RoleAssignmentFixtures
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * @param array<string, string|null> $columns
     */
    public function account(string $email, array $columns = [], string ...$roles): Account
    {
        $id = Uuid::v7()->toRfc4122();

        $this
            ->database()
            ->connection()
            ->insert('identity.app_user', Accounts::row([
                'id' => $id,
                'email' => $email,
                ...$columns,
            ]));

        new RoleGrants($this->container)->grant($id, ...$roles);

        $account = $this->service(AccountRepositoryInterface::class)->findByEmail($email);
        Assert::assertInstanceOf(Account::class, $account);

        return $account;
    }

    /**
     * @return list<string>
     */
    public function roleKeysOf(string $email): array
    {
        $keys = $this
            ->database()
            ->connection()
            ->fetchFirstColumn('SELECT r.name_key FROM identity.user_role ur JOIN identity.role r ON r.id = ur.role_id '
            . 'JOIN identity.app_user u ON u.id = ur.user_id WHERE u.email = ? ORDER BY r.name_key', [$email]);

        return array_map(strval(...), $keys);
    }

    public function assignments(): RoleAssignments
    {
        return $this->service(RoleAssignments::class);
    }

    public function lastAdministrator(): LastAdministrator
    {
        return $this->service(LastAdministrator::class);
    }

    public function forget(): void
    {
        $this->database()->entityManager()->clear();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $class): object
    {
        $service = $this->container->get($class);
        Assert::assertInstanceOf($class, $service);

        return $service;
    }

    private function database(): Database
    {
        return new Database($this->container);
    }
}
