<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Organization\Infrastructure\Persistence\Migrations;

use App\Context\Organization\Infrastructure\Persistence\Migrations\Version20261111201730;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Tests\Support\MigrationUp;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class Version20261111201730Test extends KernelTestCase
{
    public function testOnlyStageAssignmentsWhoseServiceTheAccountHoldsAsAWholeAreDeleted(): void
    {
        $sundaySchool = Structure::service('Sunday School');
        $first = Structure::stage($sundaySchool, '1st Class', position: 0);
        $third = Structure::stage($sundaySchool, '3rd Class', position: 1);
        $youth = Structure::service('Youth Meeting');
        $juniors = Structure::stage($youth, 'Juniors');
        $database = new Database(self::getContainer());
        $database->persisted($sundaySchool, $first, $third, $youth, $juniors);
        $groups = new ServedGroups(self::getContainer());
        $bishoy = Uuid::v7()->toRfc4122();
        $mina = Uuid::v7()->toRfc4122();

        $kept = [
            $groups->assign($bishoy, $sundaySchool->id()->toRfc4122()),
            $groups->assign($bishoy, $youth->id()->toRfc4122(), $juniors->id()->toRfc4122()),
            $groups->assign($mina, $sundaySchool->id()->toRfc4122(), $third->id()->toRfc4122()),
            $groups->assign($mina, $youth->id()->toRfc4122()),
        ];

        $groups->assign($bishoy, $sundaySchool->id()->toRfc4122(), $first->id()->toRfc4122());
        $groups->assign($bishoy, $sundaySchool->id()->toRfc4122(), $third->id()->toRfc4122());

        $connection = $database->connection();
        new MigrationUp($connection)->run(new Version20261111201730($connection, new NullLogger()));

        self::assertEqualsCanonicalizing(
            $kept,
            $connection->fetchFirstColumn('SELECT id FROM organization.staff_assignment'),
        );
    }
}
