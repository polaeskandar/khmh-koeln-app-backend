<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Meetings;

use App\Context\Meetings\Domain\Entity\Session;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Tests\Support\Logs;
use App\Workflow\Meetings\GenerateSessionsWorkflow;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class SessionsControllerCancelTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testACancelStoresTheReasonAndItsEventUnderTheRowLock(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $session = $this->session('2026-11-09', 1);

        $leader = new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'leader@example.org',
            'service_leader',
        ));

        $this->cancel($session, ['reason' => '  Holiday  ']);

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('id', $session->id()->toRfc4122());
        $this->assertJsonPath('status', 'canceled');

        self::assertTrue(new Logs(self::getContainer())->writesInsideLock(
            'meetings.meeting_session',
            'UPDATE meetings.meeting_session',
            'INSERT INTO meetings.session_event',
        ));

        self::assertSame(
            ['cancel_reason' => 'Holiday', 'note' => 'Holiday', 'actor_id' => $leader],
            new Database(self::getContainer())
                ->connection()
                ->fetchAssociative('SELECT s.cancel_reason, e.note, e.actor_id::text AS actor_id FROM meetings.meeting_session s '
                . "JOIN meetings.session_event e ON e.session_id = s.id AND e.type = 'canceled' WHERE s.id = ?", [$session->id()->toRfc4122()]),
        );

        $this->cancel($session, []);
        $this->assertProblem(409, 'meetings.onlyScheduledCanCancel');
    }

    public function testTheGeneratorDoesNotRecreateACanceledSession(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $session = $this->session('2026-11-09', 1);
        $this->signInWithRoles('admin@example.org', 'administrator');
        $this->cancel($session, ['reason' => str_repeat('a', times: 199) . "\u{00A0}\u{00A0}"]);
        self::assertResponseStatusCodeSame(200);
        $this->assertJsonPath('status', 'canceled');

        $generate = self::getContainer()->get(GenerateSessionsWorkflow::class);
        self::assertInstanceOf(GenerateSessionsWorkflow::class, $generate);
        $generate();

        self::assertSame(
            [['occurrence_date' => '2026-11-09', 'status' => 'canceled']],
            new Database(self::getContainer())
                ->connection()
                ->fetchAllAssociative('SELECT occurrence_date::text, status FROM meetings.meeting_session'),
        );

        self::assertSame(
            str_repeat('a', times: 199),
            new Database(self::getContainer())
                ->connection()
                ->fetchOne('SELECT cancel_reason FROM meetings.meeting_session WHERE id = ?', [$session->id()->toRfc4122()]),
        );
    }

    public function testOpenSessionsLongReasonsUnknownIdsAndAccess(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $session = $this->session('2026-11-08', 7);
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->cancel($session, ['reason' => str_repeat('a', times: 201)]);
        $this->assertFieldError('reason', 'validation.tooLong', ['max' => 200]);

        $this->jsonRequest('POST', '/api/sessions/' . Uuid::v7()->toRfc4122() . '/cancel', []);
        $this->assertProblem(404, 'notFound.session');

        $this->jsonRequest('POST', '/api/sessions/not-a-uuid/cancel', []);
        $this->assertProblem(404, 'notFound.session');

        $this->jsonRequest('POST', '/api/sessions/' . $session->id()->toRfc4122() . '/open', []);
        $this->cancel($session, []);
        $this->assertProblem(409, 'meetings.onlyScheduledCanCancel');

        foreach (['servant', 'parent'] as $role) {
            $this->signInWithRoles($role . '@example.org', $role);
            $this->cancel($session, []);
            $this->assertProblem(403, 'forbidden');
        }
    }

    /**
     * @param array<string, string> $body
     */
    private function cancel(Session $session, array $body): void
    {
        $this->jsonRequest('POST', '/api/sessions/' . $session->id()->toRfc4122() . '/cancel', $body);
    }

    private function session(string $date, int $weekday): Session
    {
        $service = Structure::service();
        new Database(self::getContainer())->persisted($service);
        $schedules = new Schedules(self::getContainer());

        return $schedules->session($schedules->weekly($service, 'Class', $weekday), $date);
    }
}
