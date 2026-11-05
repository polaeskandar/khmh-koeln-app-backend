<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Organization\Application\Service;

use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\Organization\Domain\ValueObject\DescriptionChange;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ServiceStagesTest extends KernelTestCase
{
    public function testAnEditReadsTheStageAsCommittedOnceItHoldsTheLock(): void
    {
        $service = Structure::service('Sunday School');
        $stage = Structure::stage($service, '1st Class');
        $this->database()->persisted($service, $stage);
        $id = $stage->id()->toRfc4122();

        $this->database()->entityManager()->clear();

        $this
            ->database()
            ->connection()
            ->update('organization.stage', ['name' => 'First Class', 'name_key' => 'first class'], ['id' => $id]);

        $edited = $this->stages()->edit($id, name: null, description: DescriptionChange::to('Ages six and seven'));

        self::assertSame('First Class', $edited->name()->value);
        self::assertSame('Ages six and seven', $edited->description()->value);
    }

    public function testANullDescriptionClearsAnOmittedOneStays(): void
    {
        $service = Structure::service('Sunday School');
        $stage = Structure::stage($service, '1st Class');
        $this->database()->persisted($service, $stage);
        $id = $stage->id()->toRfc4122();

        $this->stages()->edit($id, name: null, description: DescriptionChange::to('Ages six and seven'));

        self::assertSame(
            'Ages six and seven',
            $this->stages()->edit($id, '1st Class', DescriptionChange::keep())->description()->value,
        );

        self::assertNull(
            $this->stages()->edit($id, name: null, description: DescriptionChange::to(null))->description()->value,
        );
    }

    private function stages(): ServiceStages
    {
        $stages = self::getContainer()->get(ServiceStages::class);
        self::assertInstanceOf(ServiceStages::class, $stages);

        return $stages;
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
