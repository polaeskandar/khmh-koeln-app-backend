<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Organization;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Interface\Api\AssignedGroupsFixture;
use App\Tests\Support\JsonBody;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ServicesControllerScopeTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAServantSeesItsStageInItsServiceAndNothingElse(): void
    {
        $groups = $this->assignedAs(AssignedGroupsFixture::SERVANT);
        $sundaySchool = '/api/services/' . $groups->sundaySchool->id()->toRfc4122();

        $this->jsonRequest('GET', '/api/services');
        self::assertSame(['Sunday School'], $this->names('items'));
        $this->assertJsonPath('items.0.stages.0.name', '3rd Class');
        self::assertCount(1, JsonBody::asArray($this->responseJson()['items'][0]['stages'] ?? null, 'stages'));

        $this->jsonRequest('GET', $sundaySchool);
        self::assertResponseIsSuccessful();
        $this->jsonRequest('GET', $sundaySchool . '/stages');
        self::assertSame(['3rd Class'], $this->names('stages'));

        $this->jsonRequest('GET', '/api/services/insights');
        $this->assertJsonPath('items.0.serviceId', $groups->sundaySchool->id()->toRfc4122());
        $this->assertJsonPath('items.0.next.stage.name', '3rd Class');
        self::assertCount(1, JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'));

        $this->jsonRequest('GET', '/api/stages/' . $groups->third->id()->toRfc4122());
        self::assertResponseIsSuccessful();

        $this->jsonRequest('GET', '/api/stages/' . $groups->first->id()->toRfc4122());
        $this->assertProblem(404, 'notFound.stage');
        $this->jsonRequest('GET', '/api/services/' . $groups->youth->id()->toRfc4122());
        $this->assertProblem(404, 'notFound.service');
        $this->jsonRequest('GET', '/api/services/' . $groups->youth->id()->toRfc4122() . '/stages');
        $this->assertProblem(404, 'notFound.service');
    }

    public function testALeaderChangesTheServiceItLeadsAndNoOther(): void
    {
        $groups = $this->assignedAs(AssignedGroupsFixture::LEADER);
        $body = ['name' => 'Renamed', 'description' => null];

        $this->jsonRequest('GET', '/api/services');
        self::assertSame(['Sunday School'], $this->names('items'));
        self::assertCount(2, JsonBody::asArray($this->responseJson()['items'][0]['stages'] ?? null, 'stages'));

        $this->jsonRequest('PUT', '/api/services/' . $groups->sundaySchool->id()->toRfc4122(), [
            'name' => 'Sunday School',
            'description' => 'Every Sunday',
        ]);

        self::assertResponseIsSuccessful();
        $this->jsonRequest('PUT', '/api/stages/' . $groups->first->id()->toRfc4122(), $body);
        self::assertResponseIsSuccessful();

        $this->jsonRequest('POST', '/api/services/' . $groups->sundaySchool->id()->toRfc4122() . '/stages', [
            'name' => '4th Class',
        ]);

        self::assertResponseStatusCodeSame(201);

        $youth = '/api/services/' . $groups->youth->id()->toRfc4122();
        $this->jsonRequest('PUT', $youth, $body);
        $this->assertProblem(404, 'notFound.service');
        $this->jsonRequest('POST', $youth . '/stages', ['name' => 'Juniors']);
        $this->assertProblem(404, 'notFound.service');
        $this->jsonRequest('PUT', $youth . '/stages/order', ['stageIds' => []]);
        $this->assertProblem(404, 'notFound.service');
    }

    public function testALeaderOfOneStageChangesThatStageOnly(): void
    {
        $groups = $this->assignedAs(AssignedGroupsFixture::SERVANT);
        $third = '/api/stages/' . $groups->third->id()->toRfc4122();
        $first = '/api/stages/' . $groups->first->id()->toRfc4122();
        $sundaySchool = '/api/services/' . $groups->sundaySchool->id()->toRfc4122();
        $body = ['name' => 'Renamed', 'description' => null];

        $this->jsonRequest('PUT', $third, $body);
        $this->assertProblem(403, 'forbidden');

        $this->browser()->getCookieJar()->clear();
        $leader = $this->signInWithRoles('stage.leader@example.org', 'service_leader');

        new ServedGroups(self::getContainer())->assign(
            $leader,
            $groups->sundaySchool->id()->toRfc4122(),
            $groups->third->id()->toRfc4122(),
        );

        $this->jsonRequest('PUT', $third, $body);
        self::assertResponseIsSuccessful();
        $this->jsonRequest('PUT', $first, $body);
        $this->assertProblem(404, 'notFound.stage');
        $this->jsonRequest('POST', $first . '/archive');
        $this->assertProblem(404, 'notFound.stage');
        $this->jsonRequest('DELETE', $first);
        $this->assertProblem(404, 'notFound.stage');
        $this->jsonRequest('PUT', $sundaySchool, $body);
        $this->assertProblem(404, 'notFound.service');
        $this->jsonRequest('POST', $sundaySchool . '/stages', ['name' => '4th Class']);
        $this->assertProblem(404, 'notFound.service');
    }

    public function testAnUnassignedServantSeesNoServiceAndAnAdministratorSeesAll(): void
    {
        $this->assignedAs(AssignedGroupsFixture::UNASSIGNED);

        $this->jsonRequest('GET', '/api/services');
        $this->assertJsonPath('items', []);
        $this->jsonRequest('GET', '/api/services/insights');
        $this->assertJsonPath('items', []);

        $this->browser()->getCookieJar()->clear();
        $this->signInAs(AssignedGroupsFixture::ADMIN, SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/services');
        self::assertSame(['Sunday School', 'Youth Meeting'], $this->names('items'));
        self::assertCount(2, JsonBody::asArray($this->responseJson()['items'][0]['stages'] ?? null, 'stages'));
    }

    private function assignedAs(string $email): AssignedGroupsFixture
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $groups = new AssignedGroupsFixture(self::getContainer());
        $this->signInAs($email, SignInAccounts::TYPED);

        return $groups;
    }

    /**
     * @return list<mixed>
     */
    private function names(string $list): array
    {
        return array_map(
            static fn (mixed $item): mixed => JsonBody::asArray($item, 'item')['name'] ?? null,
            array_values(JsonBody::asArray($this->responseJson()[$list] ?? null, $list)),
        );
    }
}
