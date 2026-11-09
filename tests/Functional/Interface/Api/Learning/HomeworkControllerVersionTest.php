<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Database;

final class HomeworkControllerVersionTest extends ApiTestCase
{
    public function testASecondEditWithTheSameVersionIsRefusedAndTheFirstStays(): void
    {
        $task = $this->task();
        $this->signInWithRoles('daniel@example.org', 'servant');
        $this->jsonRequest('GET', $this->path($task));
        $this->assertJsonPath('version', 1);

        $this->jsonRequest('PUT', $this->path($task) . '?version=1', [
            'title' => 'First edit',
            'instructions' => 'Read it twice.',
        ]);

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('version', 2);

        $this->jsonRequest('PUT', $this->path($task) . '?version=1', [
            'title' => 'Second edit',
            'instructions' => 'Read it twice.',
        ]);

        $this->assertProblem(409, 'homework.changedMeanwhile');
        $this->jsonRequest('GET', $this->path($task));
        $this->assertJsonPath('title', 'First edit');

        $this->jsonRequest('PUT', $this->path($task), [
            'title' => 'Without a version',
            'instructions' => 'Read it twice.',
        ]);

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('version', 3);
    }

    public function testPublishingMovesTheVersion(): void
    {
        $task = $this->task();
        $this->signInWithRoles('daniel@example.org', 'servant');

        $this->jsonRequest('POST', $this->path($task) . '/publish', []);
        $this->assertJsonPath('version', 2);

        $this->jsonRequest('PUT', $this->path($task) . '?version=1', [
            'title' => 'Stale',
            'instructions' => 'Read it twice.',
        ]);

        $this->assertProblem(409, 'homework.changedMeanwhile');

        $this->jsonRequest('PUT', $this->path($task) . '?version=2', [
            'title' => 'Current',
            'instructions' => 'Read it twice.',
        ]);

        $this->assertJsonPath('version', 3);
    }

    private function path(HomeworkTask $task): string
    {
        return '/api/homework/' . $task->id()->toRfc4122();
    }

    private function task(): HomeworkTask
    {
        $task = new HomeworkTask(
            new Lessons(self::getContainer())->inThirdClass(),
            HomeworkText::of('Learn the verse', 'Read it twice.', null),
            Materials::none(),
            createdBy: null,
        );

        new Database(self::getContainer())->persisted($task);

        return $task;
    }
}
