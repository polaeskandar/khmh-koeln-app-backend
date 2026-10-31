<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Organization\Domain\Entity;

use App\Context\Organization\Domain\Entity\RegistrationService;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class RegistrationServiceTest extends KernelTestCase
{
    public function testAnAccountNamesEachServiceItServesInOnce(): void
    {
        $account = Uuid::v7();
        $sundaySchool = Structure::service(name: 'Sunday School');
        $youth = Structure::service(name: 'Youth Meeting');

        $this->database()->persisted(
            $sundaySchool,
            $youth,
            new RegistrationService($account, $sundaySchool),
            new RegistrationService($account, $youth),
        );

        $this->database()->assertRefused(Database::UNIQUE_VIOLATION, static fn (Connection $connection): int|string => $connection->insert('organization.registration_service', [
            'account_id' => $account->toRfc4122(),
            'service_id' => $youth->id()->toRfc4122(),
        ]));
    }

    public function testDeletingAServiceForgetsItsRegistrations(): void
    {
        $service = Structure::service();
        $this->database()->persisted($service, new RegistrationService(Uuid::v7(), $service));

        $this->database()->connection()->executeStatement('DELETE FROM organization.service');

        self::assertSame(
            0,
            $this->database()->connection()->fetchOne('SELECT count(*) FROM organization.registration_service'),
        );
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
