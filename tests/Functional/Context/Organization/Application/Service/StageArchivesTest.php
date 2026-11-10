<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Organization\Application\Service;

use App\Context\Organization\Application\Service\StageArchives;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Problem\Exception\RuleViolation;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class StageArchivesTest extends KernelTestCase
{
    public function testAnArchiveReadsTheStageAgainOnceTheServiceIsLocked(): void
    {
        $stage = $this->stageArchivedElsewhere(archivedAt: '2026-11-08 10:00:00+01');

        $this->expectExceptionObject(new RuleViolation('stages.alreadyArchived', ['name' => '3rd Class']));

        $this->archives()->archive($stage->id(), pause: static fn (): bool => true);
    }

    public function testARestoreReadsTheStageAgainOnceTheServiceIsLocked(): void
    {
        $service = Structure::service();
        $stage = Structure::stage($service);
        $stage->archive(Structure::now());
        new Database(self::getContainer())->persisted($service, $stage);
        $this->changeElsewhere($stage, archivedAt: null);

        $this->expectExceptionObject(new RuleViolation('stages.notArchived', ['name' => '3rd Class']));

        $this->archives()->restore($stage->id());
    }

    private function stageArchivedElsewhere(string $archivedAt): Stage
    {
        $service = Structure::service();
        $stage = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $stage);
        $this->changeElsewhere($stage, $archivedAt);

        return $stage;
    }

    private function changeElsewhere(Stage $stage, ?string $archivedAt): void
    {
        new Database(self::getContainer())
            ->connection()
            ->update('organization.stage', ['archived_at' => $archivedAt], ['id' => $stage->id()->toRfc4122()]);
    }

    private function archives(): StageArchives
    {
        $archives = self::getContainer()->get(StageArchives::class);
        self::assertInstanceOf(StageArchives::class, $archives);

        return $archives;
    }
}
