<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Attendance;

use App\Context\Meetings\Domain\Entity\Session;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\Logs;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;

final class AttendanceControllerTest extends ApiTestCase
{
    private const string ACTIVE = "status = 'active', opened_at = created_at";

    public function testTheFirstMarkIsCreatedAndARepeatReturnsIt(): void
    {
        $session = $this->session(self::ACTIVE);
        $mina = new Rosters(self::getContainer())->person('Mina');
        $first = $this->signInWithRoles('first@example.org', 'servant');

        $this->mark($session, $mina->id()->toRfc4122());
        self::assertResponseStatusCodeSame(201);
        $this->assertJsonPath('recordedBy.id', $first);
        $mark = $this->responseJson();

        $this->signInWithRoles('second@example.org', 'servant');
        $this->mark($session, $mina->id()->toRfc4122());
        self::assertResponseStatusCodeSame(200);
        self::assertSame($mark, $this->responseJson());
        self::assertSame(1, $this->rows('removed_at IS NULL'));
    }

    public function testARemovedMarkStaysAndAVisitorCanBeMarkedAgain(): void
    {
        $session = $this->session(self::ACTIVE);

        $visitor = new Rosters(self::getContainer())
            ->person('Bishoy')
            ->id()
            ->toRfc4122();

        $this->signInWithRoles('servant@example.org', 'servant');
        $this->mark($session, $visitor);
        $first = $this->responseJson();

        $this->remove($session, $visitor, ['reason' => '  wrong child ']);
        self::assertResponseStatusCodeSame(204);
        $this->remove($session, $visitor, ['reason' => 'again']);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(1, $this->rows("removal_reason = 'wrong child'"));

        $this->mark($session, $visitor);
        self::assertResponseStatusCodeSame(201);
        self::assertNotSame($first['id'] ?? null, $this->responseJson()['id'] ?? null);
        self::assertSame(2, $this->rows('true'));

        $this->remove($session, $visitor, ['reason' => str_repeat('a', times: 201)]);
        $this->assertFieldError('reason', 'validation.tooLong', ['max' => 200, 'limit' => 200]);
    }

    /**
     * @param array<string, string|int> $params
     */
    #[DataProvider('refusals')]
    public function testEachRuleHasItsCodeAndWritesNothing(
        string $set,
        string $personStatus,
        int $status,
        string $code,
        array $params = [],
    ): void {
        $session = $this->session($set);
        $mina = new Rosters(self::getContainer())->person('Mina', 'Hanna');

        new Database(self::getContainer())
            ->connection()
            ->update('people.person', ['status' => $personStatus], ['id' => $mina->id()->toRfc4122()]);

        $this->signInWithRoles('servant@example.org', 'servant');

        $this->mark($session, $mina->id()->toRfc4122());

        $this->assertProblem($status, $code, $params);
        self::assertSame(0, $this->rows('true'));
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: int, 3: string, 4?: array<string, string|int>}>
     */
    public static function refusals(): iterable
    {
        yield 'scheduled' => ['', 'active', 409, 'attendance.openFirst'];
        yield 'completed' => [
            "status = 'completed', opened_at = created_at, closed_at = created_at",
            'active',
            409,
            'attendance.closedReopen',
        ];

        yield 'canceled' => [
            "status = 'canceled', cancel_reason = 'Holiday'",
            'active',
            409,
            'meetings.sessionCanceled',
        ];

        yield 'inactive person' => [
            self::ACTIVE,
            'inactive',
            409,
            'attendance.personInactive',
            ['name' => 'Mina Hanna'],
        ];
    }

    public function testUnknownSessionsAndPeopleAreNotFound(): void
    {
        $session = $this->session(self::ACTIVE);
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest(
            'PUT',
            '/api/sessions/' . Uuid::v7()->toRfc4122() . '/attendance/' . Uuid::v7()->toRfc4122(),
        );

        $this->assertProblem(404, 'notFound.session');

        $this->mark($session, Uuid::v7()->toRfc4122());
        $this->assertProblem(404, 'notFound.person');

        $this->jsonRequest('PUT', '/api/sessions/not-a-uuid/attendance/' . Uuid::v7()->toRfc4122());
        $this->assertProblem(404, 'notFound.session');

        $this->mark($session, 'not-a-uuid');
        $this->assertProblem(404, 'notFound.person');
    }

    public function testAMarkIsWrittenWithTheSessionRowLockedAndParentsMayNotMark(): void
    {
        $session = $this->session(self::ACTIVE);

        $mina = new Rosters(self::getContainer())
            ->person('Mina')
            ->id()
            ->toRfc4122();

        $this->signInWithRoles('servant@example.org', 'servant');

        $this->mark($session, $mina);
        self::assertNotSame([], new Logs(self::getContainer())->containing('FOR UPDATE'));

        $this->signInWithRoles('parent@example.org', 'parent');
        $this->mark($session, $mina);
        $this->assertProblem(403, 'forbidden');
        $this->remove($session, $mina, []);
        $this->assertProblem(403, 'forbidden');
    }

    private function mark(Session $session, string $personId): void
    {
        $this->jsonRequest('PUT', '/api/sessions/' . $session->id()->toRfc4122() . '/attendance/' . $personId);
    }

    /**
     * @param array<string, string> $body
     */
    private function remove(Session $session, string $personId, array $body): void
    {
        $this->jsonRequest(
            'DELETE',
            '/api/sessions/' . $session->id()->toRfc4122() . '/attendance/' . $personId,
            $body,
        );
    }

    private function rows(string $where): int
    {
        $count = new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT count(*) FROM attendance.attendance WHERE ' . $where);

        self::assertIsInt($count);

        return $count;
    }

    private function session(string $set): Session
    {
        $service = Structure::service();
        new Database(self::getContainer())->persisted($service);
        $schedules = new Schedules(self::getContainer());

        return $schedules->session($schedules->weekly($service, 'Sunday class', 7), '2026-11-08', $set);
    }
}
