<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\People;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\HomeworkStatus;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Person;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Learning\Domain\Entity\Reports;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use DateTimeImmutable;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ParentChildrenControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAParentGetsTheirChildrenWithClassesAndCounts(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        [$school, $third, $first] = $this->structure();
        $mina = $this->child('Mina', 'Hanna', $school, $third);
        $mimi = $this->child('Mimi', 'Gerges', $school, $first);
        $lesson = $this->lesson($third);
        $reported = $this->task($lesson);
        $this->task($lesson);
        new Reports(self::getContainer())->stored($reported, $mina);
        $hoda = $this->signInWithRoles('hoda@example.org', 'parent');
        new Families(self::getContainer())->link($hoda, $mina);
        new Families(self::getContainer())->link($hoda, $mimi);

        $this->jsonRequest('GET', '/api/parent/children');

        self::assertResponseIsSuccessful();

        self::assertStringContainsString(
            'no-store',
            (string) $this->browser()->getResponse()->headers->get('Cache-Control'),
        );

        $this->assertJsonPath('today', '2026-11-08');

        $this->assertJsonPath('items', [
            [
                'id' => $mimi->id()->toRfc4122(),
                'name' => 'Mimi',
                'fullName' => 'Mimi Gerges',
                'initials' => 'MG',
                'stage' => '1st Class',
                'service' => 'Sunday School',
                'toDo' => 0,
                'done' => 0,
            ],
            [
                'id' => $mina->id()->toRfc4122(),
                'name' => 'Mina',
                'fullName' => 'Mina Hanna',
                'initials' => 'MH',
                'stage' => '3rd Class',
                'service' => 'Sunday School',
                'toDo' => 1,
                'done' => 1,
            ],
        ]);
    }

    public function testNoStageGivesNullAndTwoServicesShowTheFirstByName(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $youth = Structure::service('Youth Meeting');
        $choir = Structure::service('Choir');
        new Database(self::getContainer())->persisted($youth, $choir);
        $busy = $this->child('Busy', 'Child', $youth, null);
        new Rosters(self::getContainer())->place($busy, $choir, null, '2026-09-01');
        $idle = new Rosters(self::getContainer())->person('Idle', 'Child');
        $hoda = $this->signInWithRoles('hoda@example.org', 'parent');
        new Families(self::getContainer())->link($hoda, $busy);
        new Families(self::getContainer())->link($hoda, $idle);

        $this->jsonRequest('GET', '/api/parent/children');

        $this->assertJsonPath('items.0.service', 'Choir');
        $this->assertJsonPath('items.0.stage', null);
        $this->assertJsonPath('items.1.service', null);
        $this->assertJsonPath('items.1.stage', null);
    }

    public function testRevokedLinksGiveNothingAndAServantParentSeesOnlyTheirChild(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        [$school, $third] = $this->structure();
        $mina = $this->child('Mina', 'Hanna', $school, $third);
        $own = $this->child('Own', 'Child', $school, $third);
        $families = new Families(self::getContainer());

        $nadia = $this->signInWithRoles('nadia@example.org', 'parent');
        $families->link($nadia, $mina, 'revoked');
        $this->jsonRequest('GET', '/api/parent/children');
        $this->assertJsonPath('items', []);

        $both = $this->signInWithRoles('daniel@example.org', 'servant', 'parent');
        $families->link($both, $own);
        $this->jsonRequest('GET', '/api/parent/children');
        $this->assertJsonPath('items.0.name', 'Own');
        self::assertCount(1, JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'));
    }

    public function testStaffWithoutTheParentRoleAndAnonymousCallersAreRefused(): void
    {
        $this->jsonRequest('GET', '/api/parent/children');
        $this->assertProblem(401, 'auth.unauthenticated');

        foreach (['administrator', 'servant'] as $role) {
            $this->signInWithRoles($role . '@example.org', $role);
            $this->jsonRequest('GET', '/api/parent/children');
            $this->assertProblem(403, 'forbidden');
        }
    }

    private function child(string $first, string $last, Service $service, ?Stage $stage): Person
    {
        $rosters = new Rosters(self::getContainer());
        $child = $rosters->person($first, $last);
        $rosters->place($child, $service, $stage, '2026-09-01');

        return $child;
    }

    private function task(Lesson $lesson): HomeworkAssignment
    {
        $task = new HomeworkAssignment(
            $lesson,
            HomeworkText::of('Learn the verse', 'Read it.', null),
            Materials::none(),
            createdBy: null,
        );

        $task->changeState(
            $task
                ->state()
                ->withStatus(HomeworkStatus::Published)
                ->withPublishedAt(new DateTimeImmutable('2026-11-01T09:00:00+01:00')),
        );

        new Database(self::getContainer())->persisted($task);

        return $task;
    }

    private function lesson(Stage $stage): Lesson
    {
        $class = new Schedules(self::getContainer())->weekly($stage->service(), 'Sunday class', 7, stage: $stage);

        return new Lessons(self::getContainer())->stored($stage, $class, '2026-11-15');
    }

    /**
     * @return array{Service, Stage, Stage}
     */
    private function structure(): array
    {
        $school = Structure::service();
        $third = Structure::stage($school);
        $first = Structure::stage($school, '1st Class', 1);
        new Database(self::getContainer())->persisted($school, $third, $first);

        return [$school, $third, $first];
    }
}
