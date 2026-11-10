<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\People;

use App\Context\People\Domain\Entity\Person;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\AssignedGroupsFixture;
use App\Tests\Support\JsonBody;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PeopleControllerScopeTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAServantListsAndChangesThePeopleOfItsStageOnly(): void
    {
        $groups = $this->assignedAs(AssignedGroupsFixture::SERVANT);
        $people = $groups->people;
        $rename = ['firstName' => 'Mina', 'lastName' => 'Hanna', 'displayName' => 'Mimi'];

        $this->jsonRequest('GET', '/api/people');
        self::assertSame(['Mina'], $this->firstNames());
        $this->assertJsonPath('total', 1);
        $this->jsonRequest('GET', '/api/people?serviceId=' . $groups->sundaySchool->id()->toRfc4122());
        self::assertSame(['Mina'], $this->firstNames());
        $this->jsonRequest('GET', '/api/people?stageId=' . $groups->first->id()->toRfc4122());
        $this->assertProblem(404, 'notFound.stage');
        $this->jsonRequest('GET', '/api/people?serviceId=' . $groups->youth->id()->toRfc4122());
        $this->assertProblem(404, 'notFound.service');

        $this->jsonRequest('PUT', '/api/people/' . $people->mina->id()->toRfc4122(), $rename);
        self::assertResponseIsSuccessful();

        foreach ([$people->sara, $people->peter, $people->mark] as $outside) {
            $path = '/api/people/' . $outside->id()->toRfc4122();
            $this->jsonRequest('PUT', $path, $rename);
            $this->assertProblem(404, 'notFound.person');
            $this->jsonRequest('POST', $path . '/deactivate');
            $this->assertProblem(404, 'notFound.person');

            $this->jsonRequest('POST', $path . '/memberships', [
                'serviceId' => $groups->sundaySchool->id()->toRfc4122(),
            ]);

            $this->assertProblem(404, 'notFound.person');
        }
    }

    public function testAServantPlacesPeopleInItsStageOnly(): void
    {
        $groups = $this->assignedAs(AssignedGroupsFixture::SERVANT);
        $sundaySchool = $groups->sundaySchool->id()->toRfc4122();
        $mina = $groups->people->mina->id()->toRfc4122();

        $this->jsonRequest('POST', '/api/people', ['firstName' => 'Kirollos', 'lastName' => 'Girgis']);
        $this->assertFieldError('placement.serviceId', 'meetings.chooseService');
        $this->create(['serviceId' => $sundaySchool, 'stageId' => $groups->first->id()->toRfc4122()]);
        $this->assertFieldError('placement.stageId', 'meetings.chooseStage');
        $this->create(['serviceId' => $groups->youth->id()->toRfc4122()]);
        $this->assertFieldError('placement.serviceId', 'meetings.chooseService');
        $this->create(['serviceId' => $sundaySchool, 'stageId' => $groups->third->id()->toRfc4122()]);
        self::assertResponseStatusCodeSame(201);

        $this->jsonRequest('POST', '/api/people/' . $mina . '/memberships', ['serviceId' => $sundaySchool]);
        $this->assertFieldError('stageId', 'meetings.chooseStage');

        $this->jsonRequest('POST', '/api/people/' . $mina . '/memberships', [
            'serviceId' => $groups->youth->id()->toRfc4122(),
        ]);

        $this->assertFieldError('serviceId', 'meetings.chooseService');

        $this->jsonRequest('POST', '/api/memberships/' . $this->membershipOf($groups->people->mina) . '/move', [
            'stageId' => $groups->first->id()->toRfc4122(),
        ]);

        $this->assertFieldError('stageId', 'meetings.chooseStage');

        $sara = '/api/memberships/' . $this->membershipOf($groups->people->sara);
        $this->jsonRequest('POST', $sara . '/move', ['stageId' => $groups->third->id()->toRfc4122()]);
        $this->assertProblem(404, 'notFound.membership');
        $this->jsonRequest('POST', $sara . '/end');
        $this->assertProblem(404, 'notFound.membership');
    }

    public function testALeaderMovesPeopleBetweenTheStagesOfItsService(): void
    {
        $groups = $this->assignedAs(AssignedGroupsFixture::LEADER);

        $this->jsonRequest('GET', '/api/people');
        self::assertSame(['Sara', 'Mina'], $this->firstNames());

        $this->jsonRequest('POST', '/api/memberships/' . $this->membershipOf($groups->people->sara) . '/move', [
            'stageId' => $groups->third->id()->toRfc4122(),
        ]);

        self::assertResponseIsSuccessful();

        $this->jsonRequest('POST', '/api/people/' . $groups->people->mark->id()->toRfc4122() . '/memberships', [
            'serviceId' => $groups->sundaySchool->id()->toRfc4122(),
        ]);

        $this->assertProblem(404, 'notFound.person');
        $this->jsonRequest('POST', '/api/memberships/' . $this->membershipOf($groups->people->peter) . '/end');
        $this->assertProblem(404, 'notFound.membership');
    }

    public function testAnUnassignedServantSeesNobodyAndAnAdministratorEveryone(): void
    {
        $groups = $this->assignedAs(AssignedGroupsFixture::UNASSIGNED);

        $this->jsonRequest('GET', '/api/people');
        $this->assertJsonPath('items', []);
        $this->assertJsonPath('total', 0);

        $this->browser()->getCookieJar()->clear();
        $this->signInAs(AssignedGroupsFixture::ADMIN, SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/people');
        self::assertSame(['Peter', 'Sara', 'Mina', 'Mark'], $this->firstNames());

        $this->jsonRequest('POST', '/api/people/' . $groups->people->mark->id()->toRfc4122() . '/memberships', [
            'serviceId' => $groups->youth->id()->toRfc4122(),
        ]);

        self::assertResponseStatusCodeSame(201);
    }

    private function assignedAs(string $email): AssignedGroupsFixture
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $groups = new AssignedGroupsFixture(self::getContainer());
        $this->signInAs($email, SignInAccounts::TYPED);

        return $groups;
    }

    /**
     * @param array<string, string> $placement
     */
    private function create(array $placement): void
    {
        $this->jsonRequest('POST', '/api/people', [
            'firstName' => 'Kirollos',
            'lastName' => 'Girgis',
            'placement' => $placement,
        ]);
    }

    private function membershipOf(Person $person): string
    {
        return Type::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne('SELECT id FROM people.membership WHERE person_id = ? AND ends_on IS NULL', [$person->id()->toRfc4122()]),
        )->toString();
    }

    /**
     * @return list<mixed>
     */
    private function firstNames(): array
    {
        return array_map(static fn (mixed $row): mixed => JsonBody::valueAt(
            $row,
            ['person', 'firstName'],
            'person.firstName',
        ), array_values(JsonBody::asArray($this->responseJson()['items'] ?? null, 'items')));
    }
}
