<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Scheduling;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Interface\Api\AssignedGroupsFixture;
use App\Tests\Support\Interface\Api\Scheduling\ScheduleBodies;
use App\Tests\Support\JsonBody;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class SessionsControllerScopeTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAServantSeesTheMeetingsOfItsStageOnly(): void
    {
        $groups = $this->assignedAs(AssignedGroupsFixture::SERVANT);

        $this->jsonRequest('GET', '/api/schedules');
        self::assertSame(['3rd Class meeting'], $this->column('items', 'name'));
        $this->jsonRequest('GET', '/api/agenda?from=2026-11-08&to=2026-11-08');
        self::assertSame(['3rd Class meeting'], $this->column('items', 'title'));
        $this->jsonRequest('GET', '/api/dashboard/upcoming');
        self::assertSame(['3rd Class meeting'], $this->column('items', 'title'));
        $this->jsonRequest('GET', '/api/attendance/overview');

        self::assertSame(
            [$groups->meetings->thirdSession->id()->toRfc4122()],
            $this->column('sessions', 'session', 'id'),
        );

        $this->jsonRequest(
            'GET',
            '/api/attendance/counts?'
                . http_build_query(['sessionIds' => [
                    $groups->meetings->firstSession->id()->toRfc4122(),
                    $groups->meetings->thirdSession->id()->toRfc4122(),
                ]]),
        );

        self::assertSame([$groups->meetings->thirdSession->id()->toRfc4122()], $this->column('items', 'sessionId'));

        $this->jsonRequest('GET', '/api/sessions/' . $groups->meetings->thirdSession->id()->toRfc4122());
        self::assertResponseIsSuccessful();
        $this->jsonRequest('GET', '/api/schedules/' . $groups->meetings->thirdClass->id()->toRfc4122());
        self::assertResponseIsSuccessful();

        $this->jsonRequest('GET', '/api/schedules/' . $groups->meetings->firstClass->id()->toRfc4122());
        $this->assertProblem(404, 'notFound.schedule');

        foreach (['', '/attendance', '/attendance/corrections'] as $path) {
            $this->jsonRequest('GET', '/api/sessions/' . $groups->meetings->firstSession->id()->toRfc4122() . $path);
            $this->assertProblem(404, 'notFound.session');
            $this->jsonRequest('GET', '/api/sessions/' . $groups->meetings->youthSession->id()->toRfc4122() . $path);
            $this->assertProblem(404, 'notFound.session');
        }

        $this->jsonRequest('GET', '/api/stages/' . $groups->first->id()->toRfc4122() . '/attendance-history');
        $this->assertProblem(404, 'notFound.stage');
        $this->jsonRequest('GET', '/api/stages/' . $groups->third->id()->toRfc4122() . '/attendance-history');
        self::assertResponseIsSuccessful();
    }

    public function testAServantTakesAttendanceInItsStageOnly(): void
    {
        $groups = $this->assignedAs(AssignedGroupsFixture::SERVANT);
        $mina = $groups->people->mina->id()->toRfc4122();

        $this->jsonRequest(
            'PUT',
            '/api/sessions/' . $groups->meetings->thirdSession->id()->toRfc4122() . '/attendance/' . $mina,
        );

        self::assertResponseStatusCodeSame(201);

        $this->jsonRequest(
            'DELETE',
            '/api/sessions/' . $groups->meetings->thirdSession->id()->toRfc4122() . '/attendance/' . $mina,
        );

        self::assertResponseStatusCodeSame(204);
        $this->jsonRequest('POST', '/api/sessions/' . $groups->meetings->thirdSession->id()->toRfc4122() . '/close');
        self::assertResponseIsSuccessful();

        $first = '/api/sessions/' . $groups->meetings->firstSession->id()->toRfc4122();
        $this->jsonRequest('PUT', $first . '/attendance/' . $groups->people->sara->id()->toRfc4122());
        $this->assertProblem(404, 'notFound.session');
        $this->jsonRequest('DELETE', $first . '/attendance/' . $groups->people->sara->id()->toRfc4122());
        $this->assertProblem(404, 'notFound.session');

        foreach (['open', 'close'] as $transition) {
            $this->jsonRequest('POST', $first . '/' . $transition);
            $this->assertProblem(404, 'notFound.session');
        }
    }

    public function testALeaderPlansTheMeetingsOfItsServiceOnly(): void
    {
        $groups = $this->assignedAs(AssignedGroupsFixture::LEADER);
        $youth = $groups->youth->id()->toRfc4122();

        $this->jsonRequest('GET', '/api/schedules');
        self::assertSame(['1st Class meeting', '3rd Class meeting'], $this->column('items', 'name'));

        $this->jsonRequest(
            'POST',
            '/api/schedules',
            ScheduleBodies::created($groups->sundaySchool, $groups->first, '2026-11-08'),
        );

        self::assertResponseStatusCodeSame(201);

        $this->jsonRequest('POST', '/api/schedules', ScheduleBodies::created($groups->youth, null, '2026-11-08'));

        $this->assertFieldError('roster.serviceId', 'meetings.chooseService');

        $this->jsonRequest('POST', '/api/sessions', [
            'roster' => ['serviceId' => $youth, 'stageId' => null, 'mode' => 'open'],
            'title' => 'Retreat',
            'location' => null,
            'startsAt' => '2026-11-20T18:00:00+01:00',
            'endsAt' => null,
        ]);

        $this->assertFieldError('roster.serviceId', 'meetings.chooseService');

        $youthSchedule = '/api/schedules/' . $groups->meetings->youthMeeting->id()->toRfc4122();
        $this->jsonRequest('POST', $youthSchedule . '/deactivate');
        $this->assertProblem(404, 'notFound.schedule');
        $this->jsonRequest('DELETE', $youthSchedule);
        $this->assertProblem(404, 'notFound.schedule');
        $this->jsonRequest('POST', $youthSchedule . '/occurrences/2026-11-15/cancel');
        $this->assertProblem(404, 'notFound.schedule');

        $this->jsonRequest('PUT', '/api/sessions/' . $groups->meetings->youthSession->id()->toRfc4122() . '/details', [
            'title' => 'Renamed',
            'location' => null,
        ]);

        $this->assertProblem(404, 'notFound.session');
        $this->jsonRequest('POST', '/api/sessions/' . $groups->meetings->youthSession->id()->toRfc4122() . '/cancel');
        $this->assertProblem(404, 'notFound.session');
    }

    public function testAStageLeaderCannotPlanForTheServiceItself(): void
    {
        $groups = $this->assignedAs(AssignedGroupsFixture::SERVANT);
        $this->browser()->getCookieJar()->clear();
        $leader = $this->signInWithRoles('stage.leader@example.org', 'service_leader');

        new ServedGroups(self::getContainer())->assign(
            $leader,
            $groups->sundaySchool->id()->toRfc4122(),
            $groups->third->id()->toRfc4122(),
        );

        $this->jsonRequest(
            'POST',
            '/api/schedules',
            ScheduleBodies::created($groups->sundaySchool, null, '2026-11-08'),
        );

        $this->assertFieldError('roster.stageId', 'meetings.chooseStage');
        $this->jsonRequest('POST', '/api/schedules', ScheduleBodies::created($groups->youth, null, '2026-11-08'));
        $this->assertFieldError('roster.serviceId', 'meetings.chooseService');

        $this->jsonRequest(
            'POST',
            '/api/schedules',
            ScheduleBodies::created($groups->sundaySchool, $groups->third, '2026-11-08'),
        );

        self::assertResponseStatusCodeSame(201);
    }

    public function testAnUnassignedServantSeesNoMeetingAndAnAdministratorSeesAll(): void
    {
        $groups = $this->assignedAs(AssignedGroupsFixture::UNASSIGNED);

        foreach ([
            '/api/schedules' => 'items',
            '/api/agenda' => 'items',
            '/api/attendance/overview' => 'sessions',
        ] as $path => $list) {
            $this->jsonRequest('GET', $path);
            self::assertSame([], JsonBody::asArray($this->responseJson()[$list] ?? null, $list), $path);
        }

        $this->browser()->getCookieJar()->clear();
        $this->signInAs(AssignedGroupsFixture::ADMIN, SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/attendance/overview');
        self::assertCount(3, JsonBody::asArray($this->responseJson()['sessions'] ?? null, 'sessions'));
        $this->jsonRequest('GET', '/api/sessions/' . $groups->meetings->youthSession->id()->toRfc4122());
        self::assertResponseIsSuccessful();
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
    private function column(string $list, string ...$path): array
    {
        $keys = array_values($path);

        return array_map(static fn (mixed $item): mixed => JsonBody::valueAt(
            $item,
            $keys,
            implode('.', $keys),
        ), array_values(JsonBody::asArray($this->responseJson()[$list] ?? null, $list)));
    }
}
