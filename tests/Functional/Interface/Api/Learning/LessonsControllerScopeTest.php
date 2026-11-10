<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Learning\Domain\Entity\HomeworkTasks;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\AssignedGroupsFixture;
use App\Tests\Support\Interface\Api\Learning\LessonRequests;
use App\Tests\Support\JsonBody;
use Doctrine\DBAL\ArrayParameterType;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class LessonsControllerScopeTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAServantPreparesTheLessonsAndHomeworkOfItsStageOnly(): void
    {
        [$groups, $third, $first] = $this->lessonsAs(AssignedGroupsFixture::SERVANT);
        $servant = $this->idOf(AssignedGroupsFixture::SERVANT);

        $this->jsonRequest('GET', LessonRequests::lessonsOf($groups->third));
        self::assertResponseIsSuccessful();

        self::assertSame(
            [AssignedGroupsFixture::ADMIN, AssignedGroupsFixture::LEADER, AssignedGroupsFixture::SERVANT],
            $this->emailsOf($this->column('servants', 'id')),
        );

        $this->jsonRequest('GET', '/api/lessons/' . $third->id()->toRfc4122());
        self::assertResponseIsSuccessful();

        $this->jsonRequest(
            'POST',
            LessonRequests::lessonsOf($groups->third),
            LessonRequests::body($groups->meetings->thirdClass, '2026-11-22', $servant),
        );

        self::assertResponseStatusCodeSame(201);

        $this->jsonRequest('POST', '/api/lessons/' . $third->id()->toRfc4122() . '/homework', [
            'title' => 'Learn the verse',
            'instructions' => 'Read it twice.',
        ]);

        self::assertResponseStatusCodeSame(201);

        foreach (['lessons', 'homework', 'lesson-options', 'attendance-history'] as $tab) {
            $this->jsonRequest('GET', '/api/stages/' . $groups->first->id()->toRfc4122() . '/' . $tab);
            $this->assertProblem(404, 'notFound.stage');
        }

        $this->jsonRequest(
            'POST',
            LessonRequests::lessonsOf($groups->first),
            LessonRequests::body($groups->meetings->firstClass, '2026-11-22', $servant),
        );

        $this->assertProblem(404, 'notFound.stage');

        $lesson = '/api/lessons/' . $first->id()->toRfc4122();
        $this->jsonRequest('GET', $lesson);
        $this->assertProblem(404, 'notFound.lesson');
        $this->jsonRequest('POST', $lesson . '/status', ['status' => 'ready']);
        $this->assertProblem(404, 'notFound.lesson');
        $this->jsonRequest('POST', $lesson . '/archive');
        $this->assertProblem(404, 'notFound.lesson');
        $this->jsonRequest('POST', $lesson . '/homework', ['title' => 'Learn', 'instructions' => 'Read.']);
        $this->assertProblem(404, 'notFound.lesson');
    }

    public function testHomeworkOutsideTheServantsStageIsNotFound(): void
    {
        [, , $first] = $this->lessonsAs(AssignedGroupsFixture::SERVANT);
        $task = new HomeworkTasks(self::getContainer())->draft($first, dueDate: null);
        $path = '/api/homework/' . $task->id()->toRfc4122();

        foreach ([
            'GET' => '',
            'GET ' => '/progress',
            'POST' => '/publish',
            'POST ' => '/archive',
            'DELETE' => '',
        ] as $method => $suffix) {
            $this->jsonRequest(trim($method), $path . $suffix);
            $this->assertProblem(404, 'notFound.homework');
        }

        $this->jsonRequest('PUT', $path, ['title' => 'Learn', 'instructions' => 'Read.']);
        $this->assertProblem(404, 'notFound.homework');

        self::assertSame(1, new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT count(*) FROM learning.homework_assignment WHERE id = ?', [$task->id()->toRfc4122()]));
    }

    public function testALeaderPreparesEveryStageOfItsServiceAndPicksItsServants(): void
    {
        [$groups, , $first] = $this->lessonsAs(AssignedGroupsFixture::LEADER);

        $this->jsonRequest('GET', '/api/stages/' . $groups->first->id()->toRfc4122() . '/lesson-options');

        self::assertSame(
            [AssignedGroupsFixture::ADMIN, AssignedGroupsFixture::LEADER],
            $this->emailsOf($this->column('servants', 'id')),
        );

        $this->jsonRequest('POST', '/api/lessons/' . $first->id()->toRfc4122() . '/status', ['status' => 'ready']);
        self::assertResponseIsSuccessful();

        $this->jsonRequest(
            'POST',
            LessonRequests::lessonsOf($groups->first),
            LessonRequests::body(
                $groups->meetings->firstClass,
                '2026-11-22',
                $this->idOf(AssignedGroupsFixture::SERVANT),
            ),
        );

        $this->assertFieldError('responsibleId', 'lessons.chooseResponsible');
    }

    /**
     * @return array{AssignedGroupsFixture, Lesson, Lesson}
     */
    private function lessonsAs(string $email): array
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $groups = new AssignedGroupsFixture(self::getContainer());
        $lessons = new Lessons(self::getContainer());
        $third = $lessons->stored($groups->third, $groups->meetings->thirdClass, '2026-11-15');
        $first = $lessons->stored($groups->first, $groups->meetings->firstClass, '2026-11-15');
        $this->signInAs($email, SignInAccounts::TYPED);

        return [$groups, $third, $first];
    }

    private function idOf(string $email): string
    {
        return Type::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne('SELECT id FROM identity.app_user WHERE email = ?', [$email]),
        )->toString();
    }

    /**
     * @param list<mixed> $ids
     *
     * @return list<mixed>
     */
    private function emailsOf(array $ids): array
    {
        return new Database(self::getContainer())
            ->connection()
            ->fetchFirstColumn(
                'SELECT email FROM identity.app_user WHERE id::text IN (?) ORDER BY email',
                [$ids],
                [ArrayParameterType::STRING],
            );
    }

    /**
     * @return list<mixed>
     */
    private function column(string $list, string $key): array
    {
        return array_map(
            static fn (mixed $item): mixed => JsonBody::asArray($item, 'item')[$key] ?? null,
            array_values(JsonBody::asArray($this->responseJson()[$list] ?? null, $list)),
        );
    }
}
