<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Organization\Domain\Entity\Stage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\Scheduling\Domain\Entity\Schedules;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class LessonsControllerShowTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAServantGetsTheLessonWithNotesAndTheSameRowAsTheList(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$third, $lesson] = $this->lesson('2026-11-15');
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('daniel@example.org', 'servant'));

        $this->jsonRequest('GET', $this->path($lesson));

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('stage.name', '3rd Class');
        $this->assertJsonPath('service.name', 'Sunday School');
        $this->assertJsonPath('lesson.notes', 'Mina was sad');
        $this->assertJsonPath('lesson.passage', 'Luke 10:25-37');
        $this->assertJsonPath('lesson.archivedAt', null);
        $this->assertJsonPath('archived', false);
        $this->assertJsonPath('today', '2026-11-08');
        self::assertArrayNotHasKey('can', $this->responseJson());

        $detail = $this->responseJson();

        $this->jsonRequest('GET', '/api/stages/' . $third->id()->toRfc4122() . '/lessons');
        $row = JsonBody::asArray(JsonBody::asArray($this->responseJson()['items'] ?? null, 'items')[0] ?? null, 'row');
        $shown = JsonBody::asArray($detail['lesson'] ?? null, 'lesson');

        self::assertSame($row['meeting'] ?? null, $shown['meeting'] ?? null);
        self::assertSame($row['responsible'] ?? null, $shown['responsible'] ?? null);
        self::assertSame($row['homework'] ?? null, $detail['homework'] ?? null);
    }

    public function testWithoutMemberAccessTheNotesAreHidden(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [, $lesson] = $this->lesson('2026-11-15');
        $this->readerRole();
        $this->signInWithRoles('reader@example.org', 'lesson_reader');

        $this->jsonRequest('GET', $this->path($lesson));

        $this->assertJsonPath('lesson.notes', null);
        $this->assertJsonPath('lesson.objectives', 'Love your neighbour');
    }

    public function testTodayIsTheChurchDayAndAnArchivedLessonStaysReadable(): void
    {
        [, $lesson] = $this->lesson('2026-11-15');
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('daniel@example.org', 'servant'));

        self::mockTime('2026-11-14T23:30:00+01:00');
        $this->jsonRequest('GET', $this->path($lesson));
        $this->assertJsonPath('today', '2026-11-14');

        self::mockTime('2026-11-15T00:30:00+01:00');
        $this->jsonRequest('GET', $this->path($lesson));
        $this->assertJsonPath('today', '2026-11-15');

        new Database(self::getContainer())
            ->connection()
            ->update(
                'learning.lesson',
                ['archived_at' => '2026-11-15 00:10:00+01'],
                ['id' => $lesson->id()->toRfc4122()],
            );

        $this->jsonRequest('GET', $this->path($lesson));
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('lesson.archivedAt', '2026-11-15T00:10:00+01:00');
    }

    public function testArchivedStagesUnknownLessonsAndAccess(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [, $lesson] = $this->lesson('2026-11-15');

        $this->jsonRequest('GET', $this->path($lesson));
        $this->assertProblem(401, 'auth.unauthenticated');

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('daniel@example.org', 'servant'));

        new Database(self::getContainer())
            ->connection()
            ->executeStatement("UPDATE organization.stage SET archived_at = '2026-11-01 12:00:00+01'");

        $this->jsonRequest('GET', $this->path($lesson));
        $this->assertJsonPath('archived', true);

        foreach ([Uuid::v7()->toRfc4122(), 'not-a-uuid'] as $unknown) {
            $this->jsonRequest('GET', '/api/lessons/' . $unknown);
            $this->assertProblem(404, 'notFound.lesson');
        }

        $this->signInWithRoles('hoda@example.org', 'parent');
        $this->jsonRequest('GET', $this->path($lesson));
        $this->assertProblem(403, 'forbidden');
    }

    private function path(Lesson $lesson): string
    {
        return '/api/lessons/' . $lesson->id()->toRfc4122();
    }

    private function readerRole(): void
    {
        $connection = new Database(self::getContainer())->connection();
        $roleId = Uuid::v7()->toRfc4122();

        $connection->insert('access.role', [
            'id' => $roleId,
            'name' => 'Lesson reader',
            'name_key' => 'lesson_reader',
            'created_at' => '2026-11-01 12:00:00+01',
            'updated_at' => '2026-11-01 12:00:00+01',
        ]);

        $connection->executeStatement('INSERT INTO access.role_permission (role_id, permission_id) '
        . "SELECT ?, id FROM access.permission WHERE action = 'read' AND resource = 'lessons'", [$roleId]);
    }

    /**
     * @return array{Stage, Lesson}
     */
    private function lesson(string $date): array
    {
        $service = Structure::service();
        $third = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $third);
        $class = new Schedules(self::getContainer())->weekly($service, 'Sunday class', 7, stage: $third);
        $lesson = new Lessons(self::getContainer())->stored($third, $class, $date, passage: 'Luke 10:25-37');

        new Database(self::getContainer())
            ->connection()
            ->update(
                'learning.lesson',
                ['notes' => 'Mina was sad', 'objectives' => 'Love your neighbour', 'status' => 'ready'],
                ['id' => $lesson->id()->toRfc4122()],
            );

        return [$third, $lesson];
    }
}
