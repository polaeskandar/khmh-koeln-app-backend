<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Meetings;

use App\Context\Meetings\Domain\Entity\Session;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

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
        self::assertSame(1, $this->events($today, 'opened'));

        $this->post($lastWeek, 'open');
        $this->assertJsonPath('status', 'active');
    }

    public function testASessionOpensOnItsDayInBerlin(): void
    {
        $session = $this->session('2026-11-08');
        $this->signInWithRoles('servant@example.org', 'servant');

        self::mockTime('2026-11-07T23:59:00+01:00');
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
        self::assertSame(1, $this->events($session, 'closed'));
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
        $this->post($session, 'reopen', ['reason' => str_repeat('a', times: 201)]);
        $this->assertProblem(422, 'checkFields');

        $this->post($session, 'reopen', ['reason' => '  Forgot Mina  ']);
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('status', 'active');
        $this->assertJsonPath('closedAt', null);
        $this->assertJsonPath('closedBy', null);
        self::assertNotNull($this->responseJson()['openedAt'] ?? null);

        self::assertSame(
            'Forgot Mina',
            new Database(self::getContainer())
                ->connection()
                ->fetchOne("SELECT note FROM meetings.session_event WHERE type = 'reopened'"),
        );
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
    }

    private function session(string $date): Session
    {
        $service = Structure::service('Sunday School ' . $date);
        new Database(self::getContainer())->persisted($service);
        $schedules = new Schedules(self::getContainer());

        return $schedules->session($schedules->weekly($service, 'Sunday class', 7), $date);
    }

    /**
     * @param array<string, string> $body
     */
    private function post(Session $session, string $action, array $body = []): void
    {
        $this->jsonRequest('POST', '/api/sessions/' . $session->id()->toRfc4122() . '/' . $action, $body);
    }

    private function events(Session $session, string $type): int
    {
        $count = new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT count(*) FROM meetings.session_event WHERE session_id = ? AND type = ?', [
                $session->id()->toRfc4122(),
                $type,
            ]);

        self::assertIsInt($count);

        return $count;
    }
}
