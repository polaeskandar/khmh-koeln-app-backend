<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class LessonsControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAServantCreatesADraftWithCollapsedTitleAndOnlyFilledMaterials(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$third, $class] = $this->thirdClass();
        $daniel = $this->signInWithRoles('daniel@example.org', 'servant');

        $this->jsonRequest('POST', $this->lessons($third), $this->body($class, '2026-11-15', $daniel, [
            'details' => ['title' => '  The   Good  Samaritan ', 'summary' => ''],
            'plan' => [
                'materials' => [['label' => ' ', 'url' => ''], ['label' => 'Video', 'url' => 'https://example.org/v']],
            ],
        ]));

        self::assertResponseStatusCodeSame(201);
        $this->assertJsonPath('status', 'draft');
        $id = Type::of($this->responseJson()['id'] ?? null)->toString();

        self::assertSame(
            ['The Good Samaritan', null, '[{"url": "https://example.org/v", "label": "Video"}]', $daniel, '2026-11-15'],
            new Database(self::getContainer())
                ->connection()
                ->fetchNumeric('SELECT title, summary, materials::text, responsible_id::text, teaching_date::text '
                . 'FROM learning.lesson WHERE id = ?', [$id]),
        );

        $this->jsonRequest('POST', $this->lessons($third), $this->body($class, '2026-11-22', $daniel, [
            'status' => 'completed',
        ]));

        $this->assertFieldError('status', 'validation.invalidChoice', ['choices' => '"draft", "ready"']);
    }

    /**
     * @param array<string, mixed> $changes
     * @param array<string, string|int> $params
     */
    #[DataProvider('invalidLessons')]
    public function testEachInvalidFieldHasItsCode(
        array $changes,
        string $field,
        string $code,
        array $params = [],
    ): void {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$third, $class] = $this->thirdClass();
        new Lessons(self::getContainer())->stored($third, $class, '2026-11-29', 'Creation');
        $daniel = $this->signInWithRoles('daniel@example.org', 'servant');

        $this->jsonRequest(
            'POST',
            $this->lessons($third),
            array_replace_recursive($this->body($class, '2026-11-15', $daniel, []), $changes),
        );

        $this->assertFieldError($field, $code, $params);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string, 2: string, 3?: array<string, string|int>}>
     */
    public static function invalidLessons(): iterable
    {
        yield 'blank title' => [['details' => ['title' => '  ']], 'details.title', 'lessons.titleRequired'];
        yield 'long title' => [
            ['details' => ['title' => str_repeat('a', times: 161)]],
            'details.title',
            'validation.tooLong',
            ['max' => 160, 'limit' => 160],
        ];

        yield 'long passage' => [
            ['details' => ['passage' => str_repeat('a', times: 201)]],
            'details.passage',
            'validation.tooLong',
            ['max' => 200, 'limit' => 200],
        ];

        yield 'script link' => [
            ['plan' => ['materials' => [['label' => 'Bad', 'url' => 'javascript:alert(1)']]]],
            'plan.materials.0.url',
            'materials.url',
        ];

        yield 'not a preparer' => [
            ['responsibleId' => Uuid::v7()->toRfc4122()],
            'responsibleId',
            'lessons.chooseResponsible',
        ];

        yield 'taken meeting' => [
            ['meeting' => ['date' => '2026-11-29']],
            'meeting',
            'lessons.meetingHasLesson',
            ['title' => 'Creation'],
        ];
    }

    public function testAccessUnknownAndArchivedStages(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$third, $class] = $this->thirdClass();

        $this->jsonRequest('POST', $this->lessons($third), ['details' => ['title' => '']]);
        $this->assertProblem(401, 'auth.unauthenticated');

        $hoda = $this->signInWithRoles('hoda@example.org', 'parent');
        $this->jsonRequest('POST', $this->lessons($third), $this->body($class, '2026-11-15', $hoda, []));
        $this->assertProblem(403, 'lessons.prepareStageOnly');

        foreach (['service_leader' => '2026-11-15', 'administrator' => '2026-11-22'] as $role => $date) {
            $id = $this->signInWithRoles($role . '@example.org', $role);
            $this->jsonRequest('POST', $this->lessons($third), $this->body($class, $date, $id, []));
            self::assertResponseStatusCodeSame(201);
        }

        $this->jsonRequest(
            'POST',
            '/api/stages/' . Uuid::v7()->toRfc4122() . '/lessons',
            $this->body($class, '2026-11-29', $id, []),
        );

        $this->assertProblem(404, 'notFound.stage');

        new Database(self::getContainer())
            ->connection()
            ->update(
                'organization.stage',
                ['archived_at' => '2026-11-01 12:00:00+01'],
                ['id' => $third->id()->toRfc4122()],
            );

        $this->jsonRequest('POST', $this->lessons($third), $this->body($class, '2026-11-29', $id, []));
        $this->assertProblem(409, 'lessons.stageArchived', ['name' => '3rd Class']);
    }

    public function testAPatchKeepsWhatIsLeftOutAndRelinksTheLesson(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$third, $class] = $this->thirdClass();
        $leader = $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('POST', $this->lessons($third), $this->body($class, '2026-11-15', $leader, ['details' => [
            'summary' => 'Kept',
        ]]));

        $id = Type::of($this->responseJson()['id'] ?? null)->toString();

        $this->jsonRequest('PATCH', '/api/lessons/' . $id, [
            'meeting' => ['scheduleId' => $class->id()->toRfc4122(), 'date' => '2026-11-22'],
            'status' => 'ready',
        ]);

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('status', 'ready');
        self::assertSame(['The Good Samaritan', 'Kept', '2026-11-22'], $this->stored($id));

        new Database(self::getContainer())
            ->connection()
            ->executeStatement('DELETE FROM identity.user_role');

        $this->signInWithRoles('daniel@example.org', 'servant');

        $this->jsonRequest('PATCH', '/api/lessons/' . $id, [
            'meeting' => ['scheduleId' => $class->id()->toRfc4122(), 'date' => '2026-11-22'],
            'responsibleId' => $leader,
            'details' => ['title' => 'The Lost Sheep'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(['The Lost Sheep', 'Kept', '2026-11-22'], $this->stored($id));
    }

    public function testTaughtLessonsStayInThePastAndArchivedOrUnknownOnesRefuse(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$third, $class] = $this->thirdClass();
        $lesson = new Lessons(self::getContainer())->stored($third, $class, '2026-11-01');
        $database = new Database(self::getContainer())->connection();

        $database->update(
            'learning.lesson',
            ['status' => 'completed', 'completed_at' => '2026-11-01 13:00:00+01'],
            ['id' => $lesson->id()->toRfc4122()],
        );

        $daniel = $this->signInWithRoles('daniel@example.org', 'servant');
        $path = '/api/lessons/' . $lesson->id()->toRfc4122();

        $this->jsonRequest('PATCH', $path, ['meeting' => [
            'scheduleId' => $class->id()->toRfc4122(),
            'date' => '2026-11-15',
        ]]);

        $this->assertFieldError('meeting', 'lessons.taughtAfterDate');

        $this->jsonRequest('PATCH', $path, ['details' => ['summary' => 'Taught well']]);
        $this->assertFieldError('responsibleId', 'lessons.chooseResponsible');

        $this->jsonRequest('PATCH', $path, ['details' => ['summary' => 'Taught well'], 'responsibleId' => $daniel]);
        $this->assertJsonPath('status', 'completed');

        $this->jsonRequest('PATCH', $path, ['status' => 'draft']);
        $this->assertJsonPath('status', 'draft');

        self::assertNull($database->fetchOne('SELECT completed_at FROM learning.lesson WHERE id = ?', [$lesson->id()->toRfc4122()]));

        $database->update(
            'learning.lesson',
            ['archived_at' => '2026-11-02 12:00:00+01'],
            ['id' => $lesson->id()->toRfc4122()],
        );

        $this->jsonRequest('PATCH', $path, ['details' => ['summary' => 'Again']]);
        $this->assertProblem(409, 'lessons.archived');

        $this->jsonRequest('PATCH', '/api/lessons/' . Uuid::v7()->toRfc4122(), []);
        $this->assertProblem(404, 'notFound.lesson');
    }

    /**
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    private function body(Schedule $class, string $date, string $responsibleId, array $changes): array
    {
        return array_replace_recursive([
            'details' => ['title' => 'The Good Samaritan', 'passage' => 'Luke 10:25-37'],
            'meeting' => ['scheduleId' => $class->id()->toRfc4122(), 'date' => $date],
            'plan' => ['objectives' => 'Love your neighbour'],
            'responsibleId' => $responsibleId,
        ], $changes);
    }

    /**
     * @return list<mixed>
     */
    private function stored(string $id): array
    {
        $row = new Database(self::getContainer())
            ->connection()
            ->fetchNumeric('SELECT title, summary, teaching_date::text FROM learning.lesson WHERE id = ?', [$id]);

        return $row === false ? [] : $row;
    }

    private function lessons(Stage $stage): string
    {
        return '/api/stages/' . $stage->id()->toRfc4122() . '/lessons';
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
