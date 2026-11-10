<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Interface\Api\Learning\ProgressFixture;
use App\Tests\Support\JsonBody;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class HomeworkControllerProgressAccessTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testOnlyTheAllowedKeysLeaveAndTheyMatchTheStageList(): void
    {
        self::mockTime('2026-11-08T12:30:00+01:00');
        [$task] = new ProgressFixture(self::getContainer())->create();

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'leader@example.org',
            'service_leader',
        ));

        $this->jsonRequest('GET', $this->path($task));
        $progress = $this->responseJson();

        self::assertSame(['today', 'service', 'stage', 'homework', 'lesson', 'counts', 'rows'], array_keys($progress));

        self::assertSame(
            [
                'person',
                'inStage',
                'done',
                'completedAt',
                'completedBy',
                'completedAfterDueDate',
                'parentAccounts',
                'ownAccount',
                'reportedBySelf',
            ],
            array_keys($this->rows()[0] ?? []),
        );

        $homework = JsonBody::asArray($progress['homework'] ?? null, 'homework');
        $this->jsonRequest('GET', '/api/stages/' . $task->stageId()->toRfc4122() . '/homework');

        $item = JsonBody::asArray(
            JsonBody::asArray($this->responseJson()['items'] ?? null, 'items')[0] ?? null,
            'item',
        );

        self::assertSame($item['counts'] ?? null, $progress['counts'] ?? null);
        self::assertSame($item['lesson'] ?? null, $homework['lesson'] ?? null);
        self::assertArrayNotHasKey('can', $homework);
    }

    public function testStaffMayReadParentsMayNotAndUnknownTasksAreNotFound(): void
    {
        self::mockTime('2026-11-08T12:30:00+01:00');
        [$task] = new ProgressFixture(self::getContainer())->create();

        $this->jsonRequest('GET', $this->path($task));
        $this->assertProblem(401, 'auth.unauthenticated');

        foreach (['servant', 'service_leader', 'administrator'] as $role) {
            new ServedGroups(self::getContainer())->everyService($this->signInWithRoles($role . '@example.org', $role));
            $this->jsonRequest('GET', $this->path($task));
            self::assertResponseIsSuccessful();
        }

        $this->signInWithRoles('parent@example.org', 'parent');
        $this->jsonRequest('GET', $this->path($task));
        $this->assertProblem(403, 'forbidden');

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('daniel@example.org', 'servant'));

        foreach ([Uuid::v7()->toRfc4122(), 'not-a-uuid'] as $unknown) {
            $this->jsonRequest('GET', '/api/homework/' . $unknown . '/progress');
            $this->assertProblem(404, 'notFound.homework');
        }
    }

    private function path(HomeworkAssignment $task): string
    {
        return '/api/homework/' . $task->id()->toRfc4122() . '/progress';
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function rows(): array
    {
        $rows = JsonBody::asArray($this->responseJson()['rows'] ?? null, 'rows');

        return array_values(array_map(static fn (mixed $row): array => JsonBody::asArray($row, 'row'), $rows));
    }
}
