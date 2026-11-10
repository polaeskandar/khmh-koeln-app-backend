<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Organization\Application\Service;

use App\Context\Organization\Application\Service\ServiceStages;
use App\Library\Problem\Exception\NotFound;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class ServiceStagesTest extends KernelTestCase
{
    public function testAnEditReadsTheStageAsCommittedOnceItHoldsTheLock(): void
    {
        $service = Structure::service('Sunday School');
        $stage = Structure::stage($service, '1st Class');
        $this->database()->persisted($service, $stage);
        $id = $stage->id();

        $this->database()->entityManager()->clear();
        $this->database()->connection()->update('organization.stage', ['position' => 4], ['id' => $id->toRfc4122()]);

        $edited = $this->stages()->edit($id, 'First Class', description: 'Ages six and seven');

        self::assertSame('First Class', $edited->name()->value);
        self::assertSame('Ages six and seven', $edited->description()->value);
        self::assertSame(4, $edited->position());
    }

    public function testAnEditReplacesTheDescription(): void
    {
        $service = Structure::service('Sunday School');
        $stage = Structure::stage($service, '1st Class');
        $this->database()->persisted($service, $stage);
        $id = $stage->id();

        self::assertSame(
            'Ages six and seven',
            $this->stages()->edit($id, '1st Class', description: 'Ages six and seven')->description()->value,
        );

        self::assertNull($this->stages()->edit($id, '1st Class', description: null)->description()->value);
    }

    public function testAnUnknownIdIsAnUnknownStage(): void
    {
        $this->expectExceptionObject(NotFound::of('stage'));

        $this->stages()->edit(Uuid::v7(), '1st Class', description: null);
    }

    public function testAnUnknownServiceIdIsAnUnknownService(): void
    {
        $this->expectExceptionObject(NotFound::of('service'));

        $this->stages()->add(Uuid::v7(), '1st Class', description: null);
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
