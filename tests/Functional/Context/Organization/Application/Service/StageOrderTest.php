<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Organization\Application\Service;

use App\Context\Organization\Application\Service\StageOrder;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Tests\Support\CommittedRows;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

#[SkipDatabaseRollback]
final class StageOrderTest extends KernelTestCase
{
    private ?Connection $other = null;

    /**
     * @var list<Stage>
     */
    private array $stages = [];

    private ?Service $service = null;

    #[Override]
    protected function setUp(): void
    {
        $this->service = Structure::service('Locked School');

        $this->stages = [
            Structure::stage($this->service, '1st Class', 0),
            Structure::stage($this->service, '2nd Class', 1),
        ];

        new Database(self::getContainer())->persisted($this->service, ...$this->stages);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->other?->close();

        new CommittedRows(new Database(self::getContainer())->connection())->delete([
            'organization.stage' => array_map(
                static fn (Stage $stage): string => $stage->id()->toRfc4122(),
                $this->stages,
            ),
            'organization.service' => [$this->service?->id()->toRfc4122() ?? ''],
        ]);

        parent::tearDown();
    }

    public function testAReorderWaitsForTheServiceRowAndChangesNothingOnTimeout(): void
    {
        $serviceId = $this->service?->id()->toRfc4122() ?? '';
        $other = DriverManager::getConnection(new Database(self::getContainer())->connection()->getParams());
        $this->other = $other;
        $other->beginTransaction();
        $other->fetchOne('SELECT id FROM organization.service WHERE id = ? FOR UPDATE', [$serviceId]);
        $connection = new Database(self::getContainer())->connection();
        $connection->executeStatement("SET lock_timeout = '200ms'");
        $sqlState = null;

        try {
            $this->order()->reorder(Uuid::fromString($serviceId), array_reverse($this->ids()));
        } catch (DriverException $refused) {
            $sqlState = $refused->getSQLState();
        } finally {
            $other->rollBack();
            $connection->executeStatement('SET lock_timeout = 0');
        }

        self::assertSame('55P03', $sqlState);

        self::assertSame(
            ['1st Class 0', '2nd Class 1'],
            $connection->fetchFirstColumn("SELECT name || ' ' || position FROM organization.stage WHERE service_id = ? ORDER BY position", [
                $serviceId,
            ]),
        );
    }

    /**
     * @return list<string>
     */
    private function ids(): array
    {
        return array_map(static fn (Stage $stage): string => $stage->id()->toRfc4122(), $this->stages);
    }

    private function order(): StageOrder
    {
        $order = self::getContainer()->get(StageOrder::class);
        self::assertInstanceOf(StageOrder::class, $order);

        return $order;
    }
}
