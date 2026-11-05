<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class HomeworkControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAServantCreatesADraftInTheLessonsStage(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $lesson = $this->lesson();
        $daniel = $this->signInWithRoles('daniel@example.org', 'servant');

        $this->jsonRequest(
            'POST',
            $this->create($lesson),
            $this->body([
                'title' => '  Learn   the  verse ',
                'instructions' => "  Read it twice.\nThen say it aloud. ",
            ]),
        );

        self::assertResponseStatusCodeSame(201);
        $this->assertJsonPath('status', 'draft');

        self::assertSame(
            [
                'Learn the verse',
                "Read it twice.\nThen say it aloud.",
                '2026-11-22',
                $daniel,
                $lesson->stageId()->toRfc4122(),
            ],
            $this->stored($this->createdId()),
        );
    }

    public function testOneProblemListsEveryInvalidField(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $lesson = $this->lesson();
        $this->signInWithRoles('daniel@example.org', 'servant');

        $this->jsonRequest('POST', $this->create($lesson), [
            'title' => '  ',
            'instructions' => str_repeat('a', times: 2001),
            'dueDate' => '2026-11-14',
            'resources' => [['label' => 'Files', 'url' => 'ftp://example.org/a']],
        ]);

        $this->assertProblem(422, 'checkFields');

        self::assertSame(
            ['title', 'instructions', 'dueDate', 'resources.0.url'],
            array_keys(JsonBody::asArray($this->responseJson()['fieldErrors'] ?? null, 'fieldErrors')),
        );

        $this->assertFieldError('dueDate', 'homework.dueBeforeLesson');
        $this->assertFieldError('instructions', 'homework.instructionsTooLong');
    }

    /**
     * @param array<string, mixed> $changes
     * @param array<string, int> $params
     */
    #[DataProvider('invalidTasks')]
    public function testEachFieldHasItsCode(array $changes, string $field, string $code, array $params = []): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $lesson = $this->lesson();
        $this->signInWithRoles('daniel@example.org', 'servant');

        $this->jsonRequest('POST', $this->create($lesson), $this->body($changes));

        $this->assertFieldError($field, $code, $params);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string, 2: string, 3?: array<string, int>}>
     */
    public static function invalidTasks(): iterable
    {
        yield 'long title' => [['title' => str_repeat('a', times: 121)], 'title', 'validation.tooLong', ['max' => 120]];
        yield 'blank instructions' => [['instructions' => ' '], 'instructions', 'homework.instructionsRequired'];
        yield 'impossible date' => [['dueDate' => '2026-02-30'], 'dueDate', 'validation.invalidDate'];
        yield 'eleven resources' => [
            ['resources' => array_fill(start_index: 0, count: 11, value: ['label' => 'Psalm'])],
            'resources',
            'materials.tooMany',
        ];
    }

    public function testAnUnchangedDueDateStaysValidAfterTheLessonMoves(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $lesson = $this->lesson();
        $this->signInWithRoles('daniel@example.org', 'servant');
        $this->jsonRequest('POST', $this->create($lesson), $this->body([]));
        $id = $this->createdId();

        new Database(self::getContainer())
            ->connection()
            ->update('learning.lesson', ['teaching_date' => '2026-11-29'], ['id' => $lesson->id()->toRfc4122()]);

        $this->jsonRequest('PATCH', '/api/homework/' . $id, ['dueDate' => '2026-11-22', 'title' => 'Learn Psalm 23']);
        self::assertResponseIsSuccessful();
        self::assertSame('Learn Psalm 23', $this->stored($id)[0] ?? null);

        $this->jsonRequest('PATCH', '/api/homework/' . $id, ['dueDate' => '2026-11-23']);
        $this->assertFieldError('dueDate', 'homework.dueBeforeLesson');
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

    /**
     * @return list<mixed>
     */
    private function stored(string $id): array
    {
        $row = new Database(self::getContainer())
            ->connection()
            ->fetchNumeric('SELECT title, instructions, due_date::text, created_by::text, stage_id::text '
            . 'FROM learning.homework_assignment WHERE id = ?', [$id]);

        return $row === false ? [] : $row;
    }

    private function lesson(): Lesson
    {
        return new Lessons(self::getContainer())->inThirdClass();
    }
}
