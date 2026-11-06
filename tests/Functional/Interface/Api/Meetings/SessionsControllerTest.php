<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Meetings;

use App\Context\Identity\Domain\Entity\Permission;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Organization\Domain\Entity\Service;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\Roles;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use Symfony\Component\Uid\Uuid;

final class SessionsControllerTest extends ApiTestCase
{
    private ?Service $school = null;

    public function testACanceledSessionShowsItsHistoryInOrder(): void
    {
        $session = $this->sundaySession('2026-11-08');
        $adminId = $this->signInWithRoles('admin@example.org', 'administrator');
        $this->event($session, 'created', '2026-11-07 00:05:00+01', null);
        $this->event($session, 'canceled', '2026-11-07 10:00:00+01', $adminId, 'Rain');
        $this->event($session, 'edited', '2026-11-07 11:00:00+01', Uuid::v7()->toRfc4122());

        $this->jsonRequest('GET', '/api/sessions/' . $session->id()->toRfc4122());

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('session.title', 'Sunday class');
        $this->assertJsonPath('session.service.name', 'Sunday School');
        $this->assertJsonPath('session.cancelReason', 'Holiday');
        $this->assertJsonPath('history.0.type', 'created');
        $this->assertJsonPath('history.0.byGenerator', true);
        $this->assertJsonPath('history.0.actor', null);
        $this->assertJsonPath('history.1.type', 'canceled');
        $this->assertJsonPath('history.1.actor.id', $adminId);
        $this->assertJsonPath('history.1.note', 'Rain');
        $this->assertJsonPath('history.2.actor', null);
        $this->assertJsonPath('history.2.byGenerator', false);
        $this->assertJsonPath('schedule.name', 'Sunday class');
    }

    public function testTimesAreTheSchedulesWithTheRightOffset(): void
    {
        $winter = $this->sundaySession('2026-11-08');
        $summer = $this->sundaySession('2027-03-28');
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('GET', '/api/sessions/' . $winter->id()->toRfc4122());
        $this->assertJsonPath('session.startsAt', '2026-11-08T18:30:00+01:00');
        $this->assertJsonPath('session.location', 'Hall');

        $this->jsonRequest('GET', '/api/sessions/' . $summer->id()->toRfc4122());
        $this->assertJsonPath('session.startsAt', '2027-03-28T18:30:00+02:00');
    }

    public function testWithoutContentAccessReasonsAndNotesAreHidden(): void
    {
        $session = $this->sundaySession('2026-11-08');
        $this->event($session, 'canceled', '2026-11-07 10:00:00+01', null, 'Rain');

        $permission = new Database(self::getContainer())
            ->entityManager()
            ->getRepository(Permission::class)
            ->findOneBy([
                'action' => Action::Read,
                'resource' => Resource::Services,
            ]);

        self::assertInstanceOf(Permission::class, $permission);
        new Database(self::getContainer())->persisted(Roles::named('Viewer', $permission));
        $this->signInWithRoles('viewer@example.org', 'viewer');

        $this->jsonRequest('GET', '/api/sessions/' . $session->id()->toRfc4122());

        $this->assertJsonPath('session.cancelReason', null);
        $this->assertJsonPath('history.0.note', null);
    }

    public function testLeadersReadSessionsAndParentsMayNot(): void
    {
        $session = $this->sundaySession('2026-11-08');
        $this->signInWithRoles('leader@example.org', 'service_leader');
        $this->jsonRequest('GET', '/api/sessions/' . $session->id()->toRfc4122());
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('session.service.name', 'Sunday School');

        $this->jsonRequest('GET', '/api/sessions/019a3b6e-0000-7000-8000-000000000001');
        $this->assertProblem(404, 'notFound.session');

        $this->jsonRequest('GET', '/api/sessions/not-a-uuid');
        $this->assertProblem(404, 'notFound.session');

        $this->signInWithRoles('parent@example.org', 'parent');
        $this->jsonRequest('GET', '/api/sessions/' . $session->id()->toRfc4122());
        $this->assertProblem(403, 'forbidden');
    }

    private function sundaySession(string $date): Session
    {
        $schedules = new Schedules(self::getContainer());
        $schedule = $schedules->weekly($this->school(), 'Sunday class', 7);

        return $schedules->session($schedule, $date, "status = 'canceled', cancel_reason = 'Holiday'");
    }

    private function school(): Service
    {
        if ($this->school === null) {
            $this->school = Structure::service('Sunday School');
            new Database(self::getContainer())->persisted($this->school);
        }

        return $this->school;
    }

    private function event(Session $session, string $type, string $at, ?string $actorId, ?string $note = null): void
    {
        new Database(self::getContainer())
            ->connection()
            ->insert('meetings.session_event', [
                'id' => Uuid::v7()->toRfc4122(),
                'session_id' => $session->id()->toRfc4122(),
                'type' => $type,
                'occurred_at' => $at,
                'actor_id' => $actorId,
                'by_generator' => $actorId === null && $type === 'created' ? 'true' : 'false',
                'note' => $note,
            ]);
    }
}
