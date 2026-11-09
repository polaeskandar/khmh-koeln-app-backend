<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Identity\Domain\Entity;

use App\Context\Identity\Domain\Entity\Permission;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Tests\Support\Database;
use PHPUnit\Framework\Assert;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class RoleGrants
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function createRole(string $name, Action $action, Resource $resource): void
    {
        $database = new Database($this->container);

        $permission = $database
            ->entityManager()
            ->getRepository(Permission::class)
            ->findOneBy(['action' => $action, 'resource' => $resource]);

        Assert::assertInstanceOf(Permission::class, $permission);
        $database->persisted(Roles::named($name, $permission));
    }

    public function grant(string $accountId, string ...$roleKeys): void
    {
        $connection = new Database($this->container)->connection();

        foreach ($roleKeys as $roleKey) {
            $connection->executeStatement('INSERT INTO identity.user_role (user_id, role_id) SELECT ?, id FROM identity.role WHERE name_key = ?', [
                $accountId,
                $roleKey,
            ]);
        }
    }
}
