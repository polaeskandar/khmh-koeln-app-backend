<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Access\Domain\Entity;

use App\Context\Access\Domain\Entity\Permission;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
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

    public function grant(string $userId, string ...$roleKeys): void
    {
        $connection = new Database($this->container)->connection();

        foreach ($roleKeys as $roleKey) {
            $connection->executeStatement('INSERT INTO access.user_role (user_id, role_id) SELECT ?, id FROM access.role WHERE name_key = ?', [
                $userId,
                $roleKey,
            ]);
        }
    }
}
