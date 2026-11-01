<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Identity\Domain\Entity;

use App\Tests\Support\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class RoleGrants
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function grant(string $userId, string ...$roleKeys): void
    {
        $connection = new Database($this->container)->connection();

        foreach ($roleKeys as $roleKey) {
            $connection->executeStatement('INSERT INTO identity.user_role (user_id, role_id) SELECT ?, id FROM identity.role WHERE name_key = ?', [
                $userId,
                $roleKey,
            ]);
        }
    }
}
