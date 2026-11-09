<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\People\Domain\Entity\Person;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Reports;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\People\ParentFixture;
use App\Tests\Support\JsonBody;
use App\Tests\Support\Logs;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class ParentHomeworkControllerCompletionTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testARepeatedReportKeepsTheFirstAndACoParentCanUndoIt(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $fixture = new ParentFixture(self::getContainer());
        [$lesson, $mina] = $fixture->classWithMina();
        $task = $fixture->task($lesson, 'Learn the verse', null, '2026-11-01T09:00:00+01:00');
        $families = new Families(self::getContainer());
        $hoda = $this->signInWithRoles('hoda@example.org', 'parent');
        $families->link($hoda, $mina);

        $this->completion('PUT', $mina, $task);
        self::assertResponseStatusCodeSame(201);
        $this->assertJsonPath('completedByYou', true);
        $first = $this->responseJson();
        $locks = new Logs(self::getContainer())->containing(' FOR ');
        self::assertStringContainsString('people.person WHERE id = ? FOR SHARE', $locks[0] ?? '');

        self::mockTime('2026-11-08T12:10:00+01:00');
        $this->completion('PUT', $mina, $task);
        self::assertResponseStatusCodeSame(200);
        self::assertSame($first, $this->responseJson());

        $nabil = $this->signInWithRoles('nabil@example.org', 'parent');
        $families->link($nabil, $mina);
        $this->completion('PUT', $mina, $task);
        self::assertResponseStatusCodeSame(200);
        $this->assertJsonPath('completedAt', '2026-11-08T12:08:00+01:00');
        $this->assertJsonPath('completedByYou', false);
        self::assertSame(1, $this->rows('true'));

        $this->completion('DELETE', $mina, $task);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(1, $this->rows("undone_by = '" . $nabil . "'"));
        $this->completion('DELETE', $mina, $task);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(1, $this->rows('undone_at IS NOT NULL'));

        $this->jsonRequest('GET', '/api/parent/children/' . $mina->id()->toRfc4122() . '/homework');
        self::assertCount(1, JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'));

        $this->completion('PUT', $mina, $task);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(2, $this->rows('true'));
    }

    public function testInvisibleTasksForeignChildrenAndStaffWriteNothing(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $fixture = new ParentFixture(self::getContainer());
        [$lesson, $mina, $stage] = $fixture->classWithMina();
        $kiro = $fixture->child('Kiro', $stage);
        $draft = $fixture->task($lesson, 'Draft', null, null);
        $other = $fixture->task($fixture->otherLesson(), 'Other', null, '2026-11-01T09:00:00+01:00');
        $visible = $fixture->task($lesson, 'Visible', null, '2026-11-01T09:00:00+01:00');
        $hoda = $this->signInWithRoles('hoda@example.org', 'parent');
        new Families(self::getContainer())->link($hoda, $mina);

        foreach ([$draft, $other, null] as $task) {
            $this->completion('PUT', $mina, $task);
            $this->assertProblem(404, 'homework.notAvailable');
        }

        foreach ([$kiro, null] as $child) {
            $this->completion('PUT', $child, $visible);
            $this->assertProblem(404, 'notFound.person');
        }

        $this->signInWithRoles('servant@example.org', 'servant');
        $this->completion('PUT', $mina, $visible);
        $this->assertProblem(403, 'forbidden');
        self::assertSame(0, $this->rows('true'));
    }

    public function testAChildWhoLeftCanBeUndoneButNotReportedAgain(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $fixture = new ParentFixture(self::getContainer());
        [$lesson, $mina] = $fixture->classWithMina();
        $task = $fixture->task($lesson, 'Learn the verse', null, '2026-11-01T09:00:00+01:00');
        new Reports(self::getContainer())->stored($task, $mina);

        new Database(self::getContainer())
            ->connection()
            ->update('people.membership', ['ends_on' => '2026-10-31'], ['person_id' => $mina->id()->toRfc4122()]);

        $hoda = $this->signInWithRoles('hoda@example.org', 'parent');
        new Families(self::getContainer())->link($hoda, $mina);

        $this->completion('DELETE', $mina, $task);
        self::assertResponseStatusCodeSame(204);
        $this->completion('PUT', $mina, $task);
        $this->assertProblem(404, 'homework.notAvailable');
    }

    private function completion(string $method, ?Person $child, ?HomeworkTask $task): void
    {
        $childId = $child?->id()->toRfc4122() ?? Uuid::v7()->toRfc4122();
        $taskId = $task?->id()->toRfc4122() ?? Uuid::v7()->toRfc4122();

        $this->jsonRequest($method, '/api/parent/children/' . $childId . '/homework/' . $taskId . '/completion', []);
    }

    private function rows(string $where): int
    {
        $count = new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT count(*) FROM learning.homework_completion WHERE ' . $where);

        self::assertIsInt($count);

        return $count;
    }
}
