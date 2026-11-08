<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Context\Organization\Domain\Entity\Service;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Identity\Registrations;
use App\Tests\Support\JsonBody;

final class RegistrationControllerServicesTest extends ApiTestCase
{
    public function testTheListShowsActiveServicesByNameWithoutASession(): void
    {
        $this->service('Youth');
        $this->service('Choir');
        $archived = Structure::service('Archived Club');
        $archived->archive(Structure::now());
        $this->database()->persisted($archived);

        $this->jsonRequest('GET', '/api/auth/register/services');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('items.0.name', 'Choir');
        $this->assertJsonPath('items.1.name', 'Youth');
        self::assertCount(2, JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'));
    }

    public function testAServantNamesActiveServices(): void
    {
        $youth = $this->service('Youth');

        $this->jsonRequest('POST', '/api/auth/register', Registrations::servant([$youth->id()->toRfc4122()]));

        self::assertResponseStatusCodeSame(202);

        self::assertSame(
            [$youth->id()->toRfc4122()],
            $this
                ->database()
                ->connection()
                ->fetchFirstColumn('SELECT service_id FROM organization.registration_service'),
        );
    }

    public function testAServantWithoutAnActiveServiceIsAskedToChooseOne(): void
    {
        $archived = Structure::service('Archived Club');
        $archived->archive(Structure::now());
        $this->database()->persisted($archived);

        foreach ([[], [$archived->id()->toRfc4122()]] as $serviceIds) {
            $this->jsonRequest('POST', '/api/auth/register', Registrations::servant($serviceIds));

            $this->assertProblem(422, 'checkFields');
            $this->assertFieldError('registration.serviceIds', 'accounts.chooseService');
        }
    }

    public function testAParentsServicesAreIgnored(): void
    {
        $youth = $this->service('Youth');

        $this->jsonRequest('POST', '/api/auth/register', Registrations::parent([
            'registration' => ['serviceIds' => [$youth->id()->toRfc4122()]],
        ]));

        self::assertResponseStatusCodeSame(202);

        self::assertSame(
            0,
            $this->database()->connection()->fetchOne('SELECT count(*) FROM organization.registration_service'),
        );
    }

    private function service(string $name): Service
    {
        $service = Structure::service($name);
        $this->database()->persisted($service);

        return $service;
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
