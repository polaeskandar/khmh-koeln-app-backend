<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Organization\Domain\Entity;

use App\Tests\Support\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class ServedGroups
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function assign(string $accountId, string $serviceId, ?string $stageId = null): string
    {
        $id = Uuid::v7()->toRfc4122();

        new Database($this->container)
            ->connection()
            ->insert('organization.staff_assignment', [
                'id' => $id,
                'account_id' => $accountId,
                'service_id' => $serviceId,
                'stage_id' => $stageId,
                'created_at' => '2026-11-01 12:00:00+01',
            ]);

        return $id;
    }

    public function everyService(string $accountId): string
    {
        new Database($this->container)
            ->connection()
            ->executeStatement(<<<'SQL'
                INSERT INTO organization.staff_assignment (id, account_id, service_id, stage_id, created_at)
                SELECT gen_random_uuid(), ?, s.id, NULL, '2026-11-01 12:00:00+01' FROM organization.service s
                WHERE NOT EXISTS (
                  SELECT 1 FROM organization.staff_assignment a
                  WHERE a.account_id = ? AND a.service_id = s.id AND a.stage_id IS NULL
                )
                SQL, [$accountId, $accountId]);

        return $accountId;
    }
}
