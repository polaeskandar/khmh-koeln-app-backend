<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Learning\Domain\Entity\Reports;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;

final class HomeworkLifecycleControllerParityTest extends ApiTestCase
{
    private const string PUBLISHED = "status = 'published', published_at = '2026-11-01 09:00:00+01'";

    private const string ARCHIVED = "status = 'archived', archived_at = '2026-11-02 09:00:00+01'";

    private const array ACTIONS = ['unpublish', 'archive', 'restore', 'delete'];

    public function testOfferedActionsSucceedAndWithheldOnesAreRefused(): void
    {
        $lesson = new Lessons(self::getContainer())->inThirdClass();
        $tasks = [];

        foreach (self::ACTIONS as $action) {
            $tasks[$action] = $this->everyKindIn($lesson, $action);
        }

        $this->signInWithRoles('daniel@example.org', 'servant');

        foreach ($tasks as $action => $kinds) {
            foreach ($kinds as $task) {
                $this->jsonRequest('GET', $this->path($task));
                $offered = (JsonBody::asArray($this->responseJson()['can'] ?? null, 'can')[$action] ?? null) === true;
                $retry = $action === 'archive' && ($this->responseJson()['status'] ?? null) === 'archived';
                $this->act($task, $action);
                self::assertSame($offered || $retry, $this->browser()->getResponse()->isSuccessful(), $action);
            }
        }
    }

    /**
     * @return list<HomeworkAssignment>
     */
    private function everyKindIn(Lesson $lesson, string $action): array
    {
        $reported = $this->task($lesson, self::PUBLISHED);
        new Reports(self::getContainer())->stored($reported, new Families(self::getContainer())->child($action));

        return [
            $this->task($lesson, ''),
            $this->task($lesson, self::PUBLISHED),
            $reported,
            $this->task($lesson, self::ARCHIVED),
            $this->task($lesson, self::ARCHIVED . ", published_at = '2026-11-01 09:00:00+01'"),
        ];
    }

    private function act(HomeworkAssignment $task, string $action): void
    {
        if ($action === 'delete') {
            $this->jsonRequest('DELETE', $this->path($task));

            return;
        }

        $this->jsonRequest('POST', $this->path($task) . '/' . $action, []);
    }

    private function path(HomeworkAssignment $task): string
    {
        return '/api/homework/' . $task->id()->toRfc4122();
    }

    private function task(Lesson $lesson, string $set): HomeworkAssignment
    {
        $task = HomeworkAssignment::draft(
            $lesson,
            HomeworkText::of('Learn the verse', 'Read it twice.', null),
            Materials::none(),
            createdBy: null,
        );

        $database = new Database(self::getContainer());
        $database->persisted($task);

        if ($set !== '') {
            $database->connection()->executeStatement('UPDATE learning.homework_assignment SET '
            . $set
            . ' WHERE id = ?', [
                $task->id()->toRfc4122(),
            ]);
        }

        return $task;
    }
}
