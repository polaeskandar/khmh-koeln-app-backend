<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Meetings;

use App\Context\Meetings\Domain\Entity\Session;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class SessionsControllerTransitionsTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testOpeningTodaysSessionIsIdempotentAndLateOpeningWorks(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $today = $this->session('2026-11-08');
        $lastWeek = $this->session('2026-11-01');
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->post($today, 'open');
        $this->post($today, 'open');
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('status', 'active');
        self::assertCount(1, $this->notes($today, 'opened'));

        $this->post($lastWeek, 'open');
        $this->assertJsonPath('status', 'active');
    }

    public function testASessionOpensOnItsDayInBerlin(): void
    {
        self::mockTime('2026-11-07T23:59:00+01:00');
        $session = $this->session('2026-11-08');
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->post($session, 'open');
        $this->assertProblem(409, 'meetings.opensOnTheDay');

        self::mockTime('2026-11-08T00:01:00+01:00');
        $this->post($session, 'open');
        self::assertResponseIsSuccessful();
    }

    public function testClosingIsIdempotentAndNeedsAnOpenSession(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $session = $this->session('2026-11-08');
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->post($session, 'close');
        $this->assertProblem(409, 'meetings.onlyActiveCanClose');

        $this->post($session, 'open');
        $this->post($session, 'close');
        $this->post($session, 'close');
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('status', 'completed');
        $this->assertJsonPath('closedBy.name', 'Sara Hanna');
        self::assertCount(1, $this->notes($session, 'closed'));
    }

    public function testALeaderReopensWithAReason(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $session = $this->session('2026-11-08');
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->post($session, 'reopen');
        $this->assertProblem(409, 'meetings.onlyCompletedCanReopen');

        $this->post($session, 'open');
        $this->post($session, 'close');
        $openedAt = Type::of($this->responseJson()['openedAt'] ?? null)->toStringOrNull();
        $this->post($session, 'reopen', ['reason' => str_repeat('a', times: 201)]);
        $this->assertProblem(422, 'checkFields');
        $this->assertFieldError('reason', 'validation.tooLong', ['max' => 200]);

        $this->post($session, 'reopen', ['reason' => '  Forgot Mina  ']);
        $this->post($session, 'reopen', ['reason' => '  Forgot Mina  ']);
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('status', 'active');
        $this->assertJsonPath('closedAt', null);
        $this->assertJsonPath('closedBy', null);
        $this->assertJsonPath('openedAt', $openedAt);
        self::assertNotNull($openedAt);
        self::assertSame(['Forgot Mina'], $this->notes($session, 'reopened'));

        $this->post($session, 'close');
        $this->post($session, 'reopen', ['reason' => '   ']);
        self::assertSame(['Forgot Mina', null], $this->notes($session, 'reopened'));
    }

    public function testCanceledAndClosedSessionsDoNotOpen(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $canceled = $this->session('2026-11-01', set: "status = 'canceled', cancel_reason = 'Storm'");

        $closed = $this->session('2026-11-08');
        $this->signInWithRoles('servant@example.org', 'servant');
        $this->post($closed, 'open');
        $this->post($closed, 'close');

        $this->post($canceled, 'open');
        $this->assertProblem(409, 'meetings.sessionCanceled');

        $this->post($closed, 'open');
        $this->assertProblem(409, 'meetings.attendanceClosed');
    }

    public function testServantsCannotReopenAndParentsCannotOpen(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $session = $this->session('2026-11-08');
        $this->signInWithRoles('servant@example.org', 'servant');
        $this->post($session, 'open');
        $this->post($session, 'close');
        $this->post($session, 'reopen');
        $this->assertProblem(403, 'meetings.reopenLeadersOnly');

        $this->signInWithRoles('parent@example.org', 'parent');
        $this->post($session, 'open');
        $this->assertProblem(403, 'forbidden');
        $this->post($session, 'close');
        $this->assertProblem(403, 'forbidden');
        $this->post($session, 'reopen');
        $this->assertProblem(403, 'meetings.reopenLeadersOnly');
    }

    public function testAMalformedOrUnknownSessionIdIsNotFound(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('POST', '/api/sessions/not-a-uuid/open');
        $this->assertProblem(404, 'notFound.session');

        $this->jsonRequest('POST', '/api/sessions/' . Uuid::v7()->toRfc4122() . '/close');
        $this->assertProblem(404, 'notFound.session');
    }

    private function session(string $date, string $set = ''): Session
    {
        $service = Structure::service('Sunday School ' . $date);
        new Database(self::getContainer())->persisted($service);
        $schedules = new Schedules(self::getContainer());

        return $schedules->session($schedules->weekly($service, 'Sunday class', 7), $date, $set);
    }

    /**
     * @return list<?string>
     */
    private function notes(Session $session, string $type): array
    {
        $notes = new Database(self::getContainer())
            ->connection()
            ->fetchFirstColumn('SELECT note FROM meetings.session_event WHERE session_id = ? AND type = ? ORDER BY occurred_at, id', [
                $session->id()->toRfc4122(),
                $type,
            ]);

        return array_map(static fn (mixed $note): ?string => Type::of($note)->toStringOrNull(), $notes);
    }

    /**
     * @param array<string, string> $body
     */
    private function post(Session $session, string $action, array $body = []): void
    {
        $this->jsonRequest('POST', '/api/sessions/' . $session->id()->toRfc4122() . '/' . $action, $body);
    }
}
