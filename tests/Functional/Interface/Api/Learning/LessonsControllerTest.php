<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Learning\LessonRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class LessonsControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAServantCreatesADraftWithCollapsedTitleAndOnlyFilledMaterials(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$third, $class] = new LessonRequests(self::getContainer())->thirdClass();

        $daniel = new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'daniel@example.org',
            'servant',
        ));

        $this->jsonRequest(
            'POST',
            LessonRequests::lessonsOf($third),
            LessonRequests::body($class, '2026-11-15', $daniel, [
                'details' => ['title' => '  The   Good  Samaritan ', 'summary' => ''],
                'plan' => [
                    'materials' => [
                        ['label' => ' ', 'url' => ''],
                        ['label' => 'Video', 'url' => 'https://example.org/v'],
                    ],
                ],
            ]),
        );

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

        $this->jsonRequest(
            'POST',
            LessonRequests::lessonsOf($third),
            LessonRequests::body($class, '2026-11-22', $daniel, [
                'status' => 'completed',
            ]),
        );

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
        [$third, $class] = new LessonRequests(self::getContainer())->thirdClass();
        new Lessons(self::getContainer())->stored($third, $class, '2026-11-29', 'Creation');

        $daniel = new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'daniel@example.org',
            'servant',
        ));

        $this->jsonRequest(
            'POST',
            LessonRequests::lessonsOf($third),
            array_replace_recursive(LessonRequests::body($class, '2026-11-15', $daniel, []), $changes),
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
        [$third, $class] = new LessonRequests(self::getContainer())->thirdClass();

        $this->jsonRequest('POST', LessonRequests::lessonsOf($third), ['details' => ['title' => '']]);
        $this->assertProblem(401, 'auth.unauthenticated');

        $hoda = $this->signInWithRoles('hoda@example.org', 'parent');

        $this->jsonRequest(
            'POST',
            LessonRequests::lessonsOf($third),
            LessonRequests::body($class, '2026-11-15', $hoda, []),
        );

        $this->assertProblem(403, 'lessons.prepareStageOnly');

        foreach (['service_leader' => '2026-11-15', 'administrator' => '2026-11-22'] as $role => $date) {
            $id = new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
                $role . '@example.org',
                $role,
            ));

            $this->jsonRequest('POST', LessonRequests::lessonsOf($third), LessonRequests::body($class, $date, $id, []));
            self::assertResponseStatusCodeSame(201);
        }

        $this->jsonRequest(
            'POST',
            '/api/stages/' . Uuid::v7()->toRfc4122() . '/lessons',
            LessonRequests::body($class, '2026-11-29', $id, []),
        );

        $this->assertProblem(404, 'notFound.stage');

        new Database(self::getContainer())
            ->connection()
            ->update(
                'organization.stage',
                ['archived_at' => '2026-11-01 12:00:00+01'],
                ['id' => $third->id()->toRfc4122()],
            );

        $this->jsonRequest(
            'POST',
            LessonRequests::lessonsOf($third),
            LessonRequests::body($class, '2026-11-29', $id, []),
        );

        $this->assertProblem(409, 'lessons.stageArchived', ['name' => '3rd Class']);
    }
}
