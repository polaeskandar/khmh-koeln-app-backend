<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Organization;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;

final class ServicesControllerTest extends ApiTestCase
{
    public function testAnAdministratorCreatesAServiceWithACleanNameAndATone(): void
    {
        $this->signInWithRoles('admin@example.org', 'administrator');

        $response = $this->jsonRequest('POST', '/api/services', [
            'name' => '  Family   Meeting ',
            'description' => '  ',
        ]);

        self::assertSame(201, $response->getStatusCode());
        $created = $this->responseJson();
        self::assertSame('Family Meeting', $created['name'] ?? null);
        self::assertArrayHasKey('description', $created);
        self::assertNull($created['description']);
        self::assertSame([], $created['stages'] ?? null);
        self::assertContains($created['tone'] ?? null, range(start: 1, end: 8));

        $this->jsonRequest('GET', (string) $response->headers->get('Location'));
        self::assertSame($created, $this->responseJson());
    }

    #[DataProvider('nonAdministrators')]
    public function testOnlyAdministratorsCreateServices(string $role): void
    {
        $this->signInWithRoles('someone@example.org', $role);

        $this->jsonRequest('POST', '/api/services', ['name' => 'Choir']);

        $this->assertProblem(403, 'services.adminOnlyCreate');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonAdministrators(): iterable
    {
        yield 'service leader' => ['service_leader'];
        yield 'servant' => ['servant'];
    }

    /**
     * @param array<string, string|int|float|bool|null> $params
     */
    #[DataProvider('badNames')]
    public function testANameMustBeFilledShortAndFree(string $name, string $code, array $params): void
    {
        $archived = Structure::service('Old Choir');
        $archived->archive(Structure::now());
        $this->database()->persisted(Structure::service('Sunday School'), $archived);
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', '/api/services', ['name' => $name]);

        $this->assertProblem(422, 'checkFields');
        $this->assertFieldError('name', $code, $params);
    }

    /**
     * @return iterable<string, array{string, string, array<string, string|int>}>
     */
    public static function badNames(): iterable
    {
        yield 'taken' => ['  sunday   SCHOOL ', 'services.nameTaken', ['name' => 'Sunday School']];
        yield 'taken by an archived service' => ['old choir', 'services.nameTakenArchived', ['name' => 'Old Choir']];
        yield 'blank' => ['   ', 'services.nameRequired', []];
        yield 'too long' => [str_repeat('a', times: 121), 'validation.tooLong', ['max' => 120]];
    }

    public function testALeaderEditsAServiceAlsoWhenArchivedOrOnlyTheCaseChanges(): void
    {
        $service = Structure::service('Sunday School');
        $service->archive(Structure::now());
        $this->database()->persisted($service);
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('PATCH', '/api/services/' . $service->id()->toRfc4122(), ['name' => 'sunday school']);

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('name', 'sunday school');
        $this->assertJsonPath('archived', true);
    }

    public function testAServantCanNotEdit(): void
    {
        $service = Structure::service('Sunday School');
        $this->database()->persisted($service);
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('PATCH', '/api/services/' . $service->id()->toRfc4122(), ['name' => 'Kids']);

        $this->assertProblem(403, 'forbidden');
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
