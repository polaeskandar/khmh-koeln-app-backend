<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Library\Types\Model\Integers;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Access\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Learning\Domain\Entity\CopticCourse;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Database;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class MemberHomeworkControllerCompletionTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAMemberReportsOnceAndUndoesIdempotently(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $course = CopticCourse::seeded(self::getContainer());
        $course->member('nevine@example.org', 'Nevine');
        $this->signInAs('nevine@example.org', SignInAccounts::TYPED);
        $path = $this->path($course, 'letters');

        $this->jsonRequest('PUT', $path, []);
        self::assertResponseStatusCodeSame(201);
        $this->assertJsonPath('completedByYou', true);
        $this->assertJsonPath('completedAt', '2026-11-08T12:08:00+01:00');
        self::mockTime('2026-11-08T12:30:00+01:00');
        $this->jsonRequest('PUT', $path, []);
        self::assertResponseStatusCodeSame(200);
        $this->assertJsonPath('completedAt', '2026-11-08T12:08:00+01:00');

        self::assertSame(
            1,
            $this->reportsWhere(
                "undone_at IS NULL AND completed_by = (SELECT id FROM identity.app_user WHERE username = 'nevine')",
            ),
        );

        $this->jsonRequest('DELETE', $path, []);
        self::assertResponseStatusCodeSame(204);
        $this->jsonRequest('DELETE', $path, []);
        self::assertResponseStatusCodeSame(204);

        self::assertSame(
            1,
            $this->reportsWhere("undone_by = (SELECT id FROM identity.app_user WHERE username = 'nevine')"),
        );

        self::assertSame(0, $this->reportsWhere('undone_at IS NULL'));
    }

    public function testAParentsReportAndTheChildsOwnAreOne(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $course = CopticCourse::seeded(self::getContainer());
        $youssef = $course->member('youssef@example.org', 'Youssef');
        $families = new Families(self::getContainer());
        $hoda = new SignInAccounts(self::getContainer())->add('hoda@example.org', ['display_name' => 'Hoda Farag']);
        new RoleGrants(self::getContainer())->grant($hoda, 'parent');
        $families->link($hoda, $youssef);
        $taskId = $course->task('letters')->id()->toRfc4122();

        $this->signInAs('hoda@example.org', SignInAccounts::TYPED);

        $this->jsonRequest(
            'PUT',
            '/api/parent/children/' . $youssef->id()->toRfc4122() . '/homework/' . $taskId . '/completion',
            [],
        );

        self::assertResponseStatusCodeSame(201);

        $this->browser()->getCookieJar()->clear();
        $this->signInAs('youssef@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('PUT', $this->path($course, 'letters'), []);
        self::assertResponseStatusCodeSame(200);
        $this->assertJsonPath('completedBy', 'Hoda Farag');
        $this->assertJsonPath('completedByYou', false);

        $this->jsonRequest('DELETE', $this->path($course, 'letters'), []);
        self::assertResponseStatusCodeSame(204);
        $this->jsonRequest('GET', '/api/member/homework');
        $this->assertJsonPath('items.0.id', $taskId);

        $this->browser()->getCookieJar()->clear();
        $this->signInAs('hoda@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/parent/children/' . $youssef->id()->toRfc4122() . '/homework');
        $this->assertJsonPath('items.0.id', $taskId);
    }

    public function testHiddenTasksAreRefusedWithoutARowAndLeaversCanOnlyUndo(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $course = CopticCourse::seeded(self::getContainer());
        $course->member('nevine@example.org', 'Nevine');
        $leaver = $course->member('leaver@example.org', 'Leaver', until: '2026-11-01');
        $course->report('greeting', $leaver, 'leaver@example.org');
        new SignInAccounts(self::getContainer())->add('daniel@example.org');
        $this->signInAs('nevine@example.org', SignInAccounts::TYPED);

        foreach (['words', 'samaritan', 'archived'] as $hidden) {
            $this->jsonRequest('PUT', $this->path($course, $hidden), []);
            $this->assertProblem(404, 'homework.notAvailable');
        }

        $this->jsonRequest('PUT', '/api/member/homework/' . Uuid::v7()->toRfc4122() . '/completion', []);
        $this->assertProblem(404, 'homework.notAvailable');
        self::assertSame(1, $this->reportsWhere('true'));

        $this->browser()->getCookieJar()->clear();
        $this->signInAs('leaver@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('DELETE', $this->path($course, 'greeting'), []);
        self::assertResponseStatusCodeSame(204);
        $this->jsonRequest('PUT', $this->path($course, 'greeting'), []);
        $this->assertProblem(404, 'homework.notAvailable');

        $this->browser()->getCookieJar()->clear();
        $this->signInAs('daniel@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('PUT', $this->path($course, 'letters'), []);
        $this->assertProblem(403, 'forbidden');
    }

    private function path(CopticCourse $course, string $task): string
    {
        return '/api/member/homework/' . $course->task($task)->id()->toRfc4122() . '/completion';
    }

    private function reportsWhere(string $where): int
    {
        return Integers::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne('SELECT count(*) FROM learning.homework_completion WHERE ' . $where),
        );
    }
}
