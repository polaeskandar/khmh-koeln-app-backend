<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Database;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class HomeworkControllerRulesTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testEditingAPublishedTaskKeepsItsStatusAndPublishedAt(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $lesson = $this->lesson();
        $this->signInWithRoles('daniel@example.org', 'servant');
        $this->jsonRequest('POST', $this->create($lesson), $this->body([]));
        $id = $this->createdId();
        $connection = new Database(self::getContainer())->connection();

        $connection->executeStatement("UPDATE learning.homework_assignment SET status = 'published', published_at = '2026-11-08 09:00:00+01' WHERE id = ?", [
            $id,
        ]);

        $this->jsonRequest('PUT', '/api/homework/' . $id, $this->body(['instructions' => 'Say it to your parents.']));

        $this->assertJsonPath('status', 'published');

        self::assertSame(
            ['published', '2026-11-08 08:00:00'],
            $connection->fetchNumeric("SELECT status, to_char(published_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS') "
            . 'FROM learning.homework_assignment WHERE id = ?', [$id]),
        );
    }

    public function testArchivedLessonsStagesServicesAndTasksRefuse(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $lesson = $this->lesson();
        $this->signInWithRoles('daniel@example.org', 'servant');
        $this->jsonRequest('POST', $this->create($lesson), $this->body([]));
        $id = $this->createdId();
        $connection = new Database(self::getContainer())->connection();
        $archived = ['archived_at' => '2026-11-01 12:00:00+01'];

        $connection->update('organization.service', $archived, ['name' => 'Sunday School']);
        $this->jsonRequest('POST', $this->create($lesson), $this->body([]));
        $this->assertProblem(409, 'lessons.stageArchived', ['name' => 'Sunday School']);

        $connection->update('organization.service', ['archived_at' => null], ['name' => 'Sunday School']);
        $connection->update('organization.stage', $archived, ['name' => '3rd Class']);
        $this->jsonRequest('PUT', '/api/homework/' . $id, $this->body(['title' => 'Again']));
        $this->assertProblem(409, 'lessons.stageArchived', ['name' => '3rd Class']);

        $connection->update('learning.lesson', $archived, ['id' => $lesson->id()->toRfc4122()]);
        $this->jsonRequest('POST', $this->create($lesson), $this->body([]));
        $this->assertProblem(409, 'lessons.archived');

        $connection->executeStatement("UPDATE learning.homework_assignment SET status = 'archived', archived_at = now() WHERE id = ?", [
            $id,
        ]);

        $this->jsonRequest('PUT', '/api/homework/' . $id, $this->body(['title' => 'Again']));
        $this->assertProblem(409, 'homework.archivedNoEdit');
    }

    public function testAccessAndUnknownIds(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $lesson = $this->lesson();

        $this->jsonRequest('POST', $this->create($lesson), ['title' => '']);
        $this->assertProblem(401, 'auth.unauthenticated');

        $this->signInWithRoles('hoda@example.org', 'parent');
        $this->jsonRequest('POST', $this->create($lesson), ['title' => '']);
        $this->assertProblem(403, 'homework.assignStageOnly');
        $this->jsonRequest('PUT', '/api/homework/' . Uuid::v7()->toRfc4122(), []);
        $this->assertProblem(403, 'homework.changeStageOnly');

        foreach (['service_leader', 'administrator'] as $role) {
            $this->signInWithRoles($role . '@example.org', $role);
            $this->jsonRequest('POST', $this->create($lesson), $this->body([]));
            self::assertResponseStatusCodeSame(201);
        }

        foreach ([Uuid::v7()->toRfc4122(), 'not-a-uuid'] as $unknown) {
            $this->jsonRequest('POST', '/api/lessons/' . $unknown . '/homework', $this->body([]));
            $this->assertProblem(404, 'notFound.lesson');
            $this->jsonRequest('PUT', '/api/homework/' . $unknown, $this->body([]));
            $this->assertProblem(404, 'notFound.homework');
        }
    }

    /**
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    private function body(array $changes): array
    {
        return [
            'title' => 'Learn the verse',
            'instructions' => 'Read it twice.',
            'dueDate' => '2026-11-22',
            'resources' => [['label' => 'Psalm 23', 'url' => 'https://example.org/psalm']],
            ...$changes,
        ];
    }

    private function create(Lesson $lesson): string
    {
        return '/api/lessons/' . $lesson->id()->toRfc4122() . '/homework';
    }

    private function createdId(): string
    {
        return Type::of($this->responseJson()['id'] ?? null)->toString();
    }

    private function lesson(): Lesson
    {
        return new Lessons(self::getContainer())->inThirdClass();
    }
}
