<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Learning\LessonRequests;
use App\Tests\Support\JsonBody;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class LessonsControllerEditTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAnEditReplacesTheWholeLessonAndKeepsAnUnchangedResponsible(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $requests = new LessonRequests(self::getContainer());
        [$third, $class] = $requests->thirdClass();
        $leader = $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest(
            'POST',
            LessonRequests::lessonsOf($third),
            LessonRequests::body($class, '2026-11-15', $leader, [
                'details' => ['summary' => 'Gone after the edit'],
            ]),
        );

        $id = Type::of($this->responseJson()['id'] ?? null)->toString();
        $moved = LessonRequests::body($class, '2026-11-22', $leader, ['status' => 'ready']);
        $this->jsonRequest('PUT', '/api/lessons/' . $id, $moved);

        self::assertResponseStatusCodeSame(200);
        $this->assertJsonPath('status', 'ready');
        self::assertSame(['The Good Samaritan', null, '2026-11-22'], $requests->stored($id));

        new Database(self::getContainer())
            ->connection()
            ->executeStatement('DELETE FROM identity.user_role');

        $this->signInWithRoles('daniel@example.org', 'servant');

        $this->jsonRequest('PUT', '/api/lessons/' . $id, LessonRequests::body($class, '2026-11-22', $leader, [
            'details' => ['title' => 'The Lost Sheep'],
        ]));

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('status', 'ready');
        self::assertSame(['The Lost Sheep', null, '2026-11-22'], $requests->stored($id));
    }

    public function testTheRuleErrorsOfEveryPartAreReportedTogether(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$third, $class] = new LessonRequests(self::getContainer())->thirdClass();
        new Lessons(self::getContainer())->stored($third, $class, '2026-11-29', 'Creation');
        $daniel = $this->signInWithRoles('daniel@example.org', 'servant');

        $this->jsonRequest(
            'POST',
            LessonRequests::lessonsOf($third),
            LessonRequests::body($class, '2026-11-29', $daniel, [
                'details' => ['title' => "\u{00A0}"],
                'plan' => ['materials' => [['label' => 'Bad', 'url' => 'javascript:alert(1)']]],
                'responsibleId' => Uuid::v7()->toRfc4122(),
            ]),
        );

        self::assertResponseStatusCodeSame(422);
        $fields = array_keys(JsonBody::asArray($this->responseJson()['fieldErrors'] ?? null, '"fieldErrors"'));
        sort($fields);
        self::assertSame(['details.title', 'meeting', 'plan.materials.0.url', 'responsibleId'], $fields);
        $this->assertFieldError('meeting', 'lessons.meetingHasLesson', ['title' => 'Creation']);
    }

    public function testTaughtLessonsStayInThePastAndArchivedOrUnknownOnesRefuse(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$third, $class] = new LessonRequests(self::getContainer())->thirdClass();
        $lesson = new Lessons(self::getContainer())->stored($third, $class, '2026-11-01');
        $database = new Database(self::getContainer())->connection();
        $id = $lesson->id()->toRfc4122();

        $database->update(
            'learning.lesson',
            ['status' => 'completed', 'completed_at' => '2026-11-01 13:00:00+01'],
            ['id' => $id],
        );

        $daniel = $this->signInWithRoles('daniel@example.org', 'servant');
        $path = '/api/lessons/' . $id;

        $this->jsonRequest('PUT', $path, LessonRequests::body($class, '2026-11-15', $daniel));
        $this->assertFieldError('meeting', 'lessons.taughtAfterDate');

        $withoutResponsible = LessonRequests::body($class, '2026-11-01', $daniel);
        unset($withoutResponsible['responsibleId']);
        $this->jsonRequest('PUT', $path, $withoutResponsible);
        $this->assertFieldError('responsibleId', 'lessons.chooseResponsible');

        $this->jsonRequest('PUT', $path, LessonRequests::body($class, '2026-11-01', $daniel));
        $this->assertJsonPath('status', 'completed');

        $this->jsonRequest('PUT', $path, LessonRequests::body($class, '2026-11-01', $daniel, ['status' => 'draft']));
        $this->assertJsonPath('status', 'draft');
        self::assertNull($database->fetchOne('SELECT completed_at FROM learning.lesson WHERE id = ?', [$id]));

        $database->update('learning.lesson', ['archived_at' => '2026-11-02 12:00:00+01'], ['id' => $id]);
        $this->jsonRequest('PUT', $path, LessonRequests::body($class, '2026-11-01', $daniel));
        $this->assertProblem(409, 'lessons.archived');

        foreach ([Uuid::v7()->toRfc4122(), 'not-a-uuid'] as $unknown) {
            $this->jsonRequest('PUT', '/api/lessons/' . $unknown, LessonRequests::body($class, '2026-11-01', $daniel));
            $this->assertProblem(404, 'notFound.lesson');
        }
    }
}
