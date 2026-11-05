<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Time\Model\LocalDate;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class HomeworkControllerParityTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string PUBLISHED = "status = 'published', published_at = '2026-11-01 09:00:00+01'";

    public function testEachItemMatchesItsTaskAndItsFlagsHoldOnTheWriteEndpoints(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$stage, $class] = $this->thirdClass();
        $lesson = new Lessons(self::getContainer())->stored($stage, $class, '2026-11-15');
        $this->task($lesson, 'Published', null, self::PUBLISHED);
        $this->task($lesson, 'Draft', null, '');
        $this->task($lesson, 'Archived', null, "status = 'archived', archived_at = '2026-11-07 09:00:00+01'");
        $this->signInWithRoles('daniel@example.org', 'servant');
        $this->jsonRequest('GET', '/api/stages/' . $stage->id()->toRfc4122() . '/homework');
        $items = JsonBody::asArray($this->responseJson()['items'] ?? null, 'items');

        foreach (array_map(static fn (mixed $raw): array => JsonBody::asArray($raw, 'item'), $items) as $item) {
            $id = Type::of($item['id'] ?? null)->toString();
            $can = JsonBody::asArray($item['can'] ?? null, 'can');

            $this->jsonRequest('GET', '/api/homework/' . $id);
            self::assertSame($item['counts'] ?? null, $this->responseJson()['counts'] ?? null);
            self::assertSame($can, $this->responseJson()['can'] ?? null);

            $this->jsonRequest('PATCH', '/api/homework/' . $id, ['title' => 'Renamed']);
            self::assertSame($can['edit'] ?? null, $this->browser()->getResponse()->isSuccessful());

            $this->jsonRequest('POST', '/api/homework/' . $id . '/publish', []);
            $offered = ($can['publish'] ?? null) === true || ($item['status'] ?? null) === 'published';
            self::assertSame($offered, $this->browser()->getResponse()->isSuccessful());
        }
    }

    private function task(Lesson $lesson, string $title, ?string $dueDate, string $set): HomeworkAssignment
    {
        $task = HomeworkAssignment::draft(
            $lesson,
            HomeworkText::of($title, 'Read it twice.', $dueDate === null ? null : LocalDate::of($dueDate)),
            Materials::none(),
            createdBy: null,
        );

        $database = new Database(self::getContainer());
        $database->persisted($task);

        if ($set !== '') {
            $database->connection()->executeStatement('UPDATE learning.homework_assignment SET '
            . $set
            . ' WHERE id = ?', [$task->id()->toRfc4122()]);
        }

        return $task;
    }

    /**
     * @return array{Stage, Schedule}
     */
    private function thirdClass(): array
    {
        $service = Structure::service();
        $third = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $third);

        return [$third, new Schedules(self::getContainer())->weekly($service, 'Sunday class', 7, stage: $third)];
    }
}
