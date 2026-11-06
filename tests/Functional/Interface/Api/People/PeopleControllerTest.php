<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\People;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class PeopleControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAPersonJoinsTheRosterFromChurchToday(): void
    {
        self::mockTime('2026-11-07T23:30:00Z');
        [$service, $third] = $this->structure();
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->create([
            'firstName' => ' Mina ',
            'lastName' => 'Hanna',
            'placement' => [
                'serviceId' => $service->id()->toRfc4122(),
                'stageId' => $third->id()->toRfc4122(),
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertJsonPath('person.firstName', 'Mina');
        $this->assertJsonPath('person.status', 'active');
        $this->assertJsonPath('membership.stageId', $third->id()->toRfc4122());
        $this->assertJsonPath('membership.startsOn', '2026-11-08');
        $this->assertJsonPath('membership.endsOn', null);

        $this->create(['firstName' => 'Marina', 'lastName' => 'Hanna']);
        self::assertResponseStatusCodeSame(201);
        $this->assertJsonPath('membership', null);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string|int> $params
     */
    #[DataProvider('invalidPeople')]
    public function testEachInvalidFieldHasItsCodeAndNothingIsWritten(
        array $body,
        string $field,
        string $code,
        array $params = [],
    ): void {
        [$service, $third] = $this->structure();
        $archived = Structure::stage($service, 'Old Class', position: 1);
        $archived->archive(Structure::now());
        new Database(self::getContainer())->persisted($archived);

        $ids = [
            'service' => $service->id()->toRfc4122(),
            'stage' => $third->id()->toRfc4122(),
            'archived' => $archived->id()->toRfc4122(),
            'unknown' => Uuid::v7()->toRfc4122(),
        ];

        $this->signInWithRoles('servant@example.org', 'servant');
        $placement = JsonBody::asArray($body['placement'] ?? [], 'placement');

        $this->create([
            ...$body,
            'placement' => $placement === []
                ? null
                : array_map(static fn (mixed $id): ?string => is_string($id) ? $ids[$id] ?? $id : null, $placement),
        ]);

        $this->assertFieldError($field, $code, $params);

        self::assertSame(
            0,
            new Database(self::getContainer())
                ->connection()
                ->fetchOne('SELECT count(*) FROM people.person'),
        );
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string, 2: string, 3?: array<string, string|int>}>
     */
    public static function invalidPeople(): iterable
    {
        $names = ['firstName' => 'Mina', 'lastName' => 'Hanna'];

        yield 'blank first name' => [
            ['firstName' => '  ', 'lastName' => 'Hanna'],
            'firstName',
            'people.firstNameRequired',
        ];

        yield 'blank last name' => [['firstName' => 'Mina', 'lastName' => ''], 'lastName', 'people.lastNameRequired'];
        yield 'long first name' => [
            ['firstName' => str_repeat('a', times: 81), 'lastName' => 'Hanna'],
            'firstName',
            'validation.tooLong',
            ['max' => 80],
        ];

        yield 'unknown service' => [
            [...$names, 'placement' => ['serviceId' => 'unknown']],
            'placement.serviceId',
            'meetings.chooseService',
        ];

        yield 'unknown stage' => [
            [...$names, 'placement' => ['serviceId' => 'service', 'stageId' => 'unknown']],
            'placement.stageId',
            'meetings.chooseStage',
        ];

        yield 'archived stage' => [
            [...$names, 'placement' => ['serviceId' => 'service', 'stageId' => 'archived']],
            'placement.stageId',
            'meetings.stageArchived',
            ['name' => 'Old Class'],
        ];
    }

    public function testBlankNamesAreReportedTogether(): void
    {
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->create(['firstName' => ' ', 'lastName' => ' ']);

        $errors = JsonBody::asArray($this->responseJson()['fieldErrors'] ?? null, 'fieldErrors');
        self::assertSame(['firstName', 'lastName'], array_keys($errors));
    }

    public function testNameLengthsCountOnceTheSpacesAreCollapsed(): void
    {
        $this->signInWithRoles('servant@example.org', 'servant');
        $spaced = str_repeat('a', times: 39) . '   ' . str_repeat('b', times: 39);

        $this->create(['firstName' => $spaced, 'lastName' => 'Hanna']);

        self::assertResponseStatusCodeSame(201);
        $this->assertJsonPath('person.firstName', str_repeat('a', times: 39) . ' ' . str_repeat('b', times: 39));
    }

    public function testANamesakeIsReportedUntilTheDuplicateIsConfirmed(): void
    {
        $rosters = new Rosters(self::getContainer());
        $mina = $rosters->person('Mina', 'Hanna');
        $inactive = $rosters->person('MINA', 'hanna');

        new Database(self::getContainer())
            ->connection()
            ->update('people.person', ['status' => 'inactive'], ['id' => $inactive->id()->toRfc4122()]);

        $this->signInWithRoles('servant@example.org', 'servant');

        $this->create(['firstName' => '  mina   ', 'lastName' => 'HANNA ']);

        $this->assertProblem(409, 'people.duplicate', ['count' => 2, 'name' => 'mina HANNA']);
        $candidates = JsonBody::asArray($this->responseJson()['candidates'] ?? null, 'candidates');
        self::assertCount(1, $candidates);
        $this->assertJsonPath('candidates.0.id', $mina->id()->toRfc4122());

        self::assertSame(
            ['id', 'firstName', 'lastName', 'displayName', 'groups'],
            array_keys(JsonBody::asArray($candidates[0] ?? null, 'candidate')),
        );

        $this->create(['firstName' => 'Mina', 'lastName' => 'Hanna', 'confirmDuplicate' => true]);
        self::assertResponseStatusCodeSame(201);
    }

    public function testPeopleAreRenamedByAccountsThatMayChangeMembers(): void
    {
        $mina = new Rosters(self::getContainer())->person('Mina', 'Hanna');
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('PUT', '/api/people/' . $mina->id()->toRfc4122(), [
            'firstName' => 'Mina',
            'lastName' => '  Hanna   Gerges ',
            'displayName' => 'Mimi',
        ]);

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('firstName', 'Mina');
        $this->assertJsonPath('lastName', 'Hanna Gerges');
        $this->assertJsonPath('displayName', 'Mimi');

        $this->jsonRequest('PUT', '/api/people/' . $mina->id()->toRfc4122(), [
            'firstName' => 'Mina',
            'lastName' => 'Hanna',
        ]);

        $this->assertJsonPath('displayName', null);

        $this->jsonRequest('PUT', '/api/people/' . Uuid::v7()->toRfc4122(), [
            'firstName' => 'Marina',
            'lastName' => 'H',
        ]);

        $this->assertProblem(404, 'notFound.person');

        $this->jsonRequest('PUT', '/api/people/not-a-uuid', ['firstName' => 'Marina', 'lastName' => 'H']);
        $this->assertProblem(404, 'notFound.person');
    }

    public function testParentsMayNeitherAddNorRenamePeople(): void
    {
        $mina = new Rosters(self::getContainer())->person('Mina', 'Hanna');
        $this->signInWithRoles('parent@example.org', 'parent');

        $this->create(['firstName' => 'Marina', 'lastName' => 'Hanna']);
        $this->assertProblem(403, 'people.addNotAllowed');

        $this->jsonRequest('PUT', '/api/people/' . $mina->id()->toRfc4122(), [
            'firstName' => 'Marina',
            'lastName' => 'H',
        ]);

        $this->assertProblem(403, 'people.changeNotAllowed');
    }

    /**
     * @param array<string, mixed> $body
     */
    private function create(array $body): void
    {
        $this->jsonRequest('POST', '/api/people', $body);
    }

    /**
     * @return array{Service, Stage}
     */
    private function structure(): array
    {
        $service = Structure::service();
        $third = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $third);

        return [$service, $third];
    }
}
