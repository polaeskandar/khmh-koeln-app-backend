<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\ParentPortal;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\People\Domain\Entity\Person;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Reports;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\ParentPortal\ParentFixture;
use App\Tests\Support\JsonBody;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class ParentHomeworkControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testToDoAndDoneHaveTheirOrderAndTodayIsTheBerlinDate(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        [$lesson, $mina] = $this->fixture()->classWithMina();
        $this->fixture()->task($lesson, 'Older tie', '2026-11-15', '2026-11-01T09:00:00+01:00');
        $this->fixture()->task($lesson, 'Newer tie', '2026-11-15', '2026-11-03T09:00:00+01:00');
        $this->fixture()->task($lesson, 'Undated', null, '2026-11-05T09:00:00+01:00');
        $this->fixture()->task($lesson, 'Sooner', '2026-11-10', '2026-11-01T09:00:00+01:00');

        new Reports(self::getContainer())->stored(
            $this->fixture()->task($lesson, 'Done first', null, '2026-11-01T09:00:00+01:00'),
            $mina,
            '2026-11-05T10:00:00+01:00',
        );

        new Reports(self::getContainer())->stored(
            $this->fixture()->task($lesson, 'Done last', null, '2026-11-01T09:00:00+01:00'),
            $mina,
            '2026-11-07T10:00:00+01:00',
        );

        $hoda = $this->signInWithRoles('hoda@example.org', 'parent');
        new Families(self::getContainer())->link($hoda, $mina);

        $this->jsonRequest('GET', $this->list($mina));
        self::assertSame(['Sooner', 'Newer tie', 'Older tie', 'Undated'], $this->titles());
        $this->assertJsonPath('today', '2026-11-08');

        $this->jsonRequest('GET', $this->list($mina) . '?state=done');
        self::assertSame(['Done last', 'Done first'], $this->titles());
        $this->assertJsonPath('items.0.completedAt', '2026-11-07T10:00:00+01:00');
        $this->assertJsonPath('items.0.stage', '3rd Class');
        $this->assertJsonPath('items.0.lessonTitle', 'The Good Samaritan');

        $this->jsonRequest('GET', $this->list($mina) . '?state=open');
        $this->assertFieldError('state', 'validation.invalidChoice', ['choices' => '"todo", "done"']);

        self::mockTime('2026-11-08T23:30:00Z');
        $this->jsonRequest('GET', $this->list($mina));
        $this->assertJsonPath('today', '2026-11-09');
    }

    public function testAnythingNotTheParentsIsTheSameNotFound(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        [$lesson, $mina, $stage] = $this->fixture()->classWithMina();
        $kiro = $this->fixture()->child('Kiro', $stage);
        $published = $this->fixture()->task($lesson, 'Visible', null, '2026-11-01T09:00:00+01:00');
        $draft = $this->fixture()->task($lesson, 'Draft', null, null);

        $other = $this->fixture()->task(
            $this->fixture()->otherLesson(),
            'Other stage',
            null,
            '2026-11-01T09:00:00+01:00',
        );

        $families = new Families(self::getContainer());
        $hoda = $this->signInWithRoles('hoda@example.org', 'parent');
        $families->link($hoda, $mina);
        $families->link($families->account('kiro-parent@example.org'), $kiro);

        $bodies = array_map(function (string $path): string {
            $this->jsonRequest('GET', $path);
            $this->assertProblem(404, 'notFound.person');

            return (string) $this->browser()->getResponse()->getContent();
        }, [
            $this->list($kiro),
            $this->list(null),
            $this->detail($kiro, $published),
            '/api/parent/children/not-a-uuid/homework',
        ]);

        self::assertCount(1, array_unique($bodies));

        $missing = array_map(function (string $path): string {
            $this->jsonRequest('GET', $path);
            $this->assertProblem(404, 'homework.notAvailable');

            return (string) $this->browser()->getResponse()->getContent();
        }, [
            $this->detail($mina, $draft),
            $this->detail($mina, $other),
            $this->detail($mina, null),
            $this->list($mina) . '/not-a-uuid',
        ]);

        self::assertCount(1, array_unique($missing));

        $this->jsonRequest('GET', '/api/parent/children/nope/homework');
        self::assertResponseStatusCodeSame(404);

        new Database(self::getContainer())
            ->connection()
            ->executeStatement("UPDATE people.guardian_relationship SET status = 'revoked', revoked_at = now() WHERE user_id = ?", [
                $hoda,
            ]);

        $this->jsonRequest('GET', $this->list($mina));
        self::assertSame($bodies[0], (string) $this->browser()->getResponse()->getContent());
    }

    public function testTheTaskPageHoldsOnlyItsKeysAndAnotherParentsReport(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        [$lesson, $mina] = $this->fixture()->classWithMina();
        $task = $this->fixture()->task($lesson, 'Learn the verse', '2026-11-15', '2026-11-01T09:00:00+01:00');
        $families = new Families(self::getContainer());
        $hoda = $families->account('hoda@example.org', ['display_name' => 'Hoda Farag']);
        $families->link($hoda, $mina);
        new Reports(self::getContainer())->stored($task, $mina, '2026-11-07T10:00:00+01:00', by: $hoda);
        $nabil = $this->signInWithRoles('nabil@example.org', 'parent');
        $families->link($nabil, $mina);

        $this->jsonRequest('GET', $this->detail($mina, $task));

        self::assertResponseIsSuccessful();
        self::assertSame(['today', 'child', 'task', 'lesson'], array_keys($this->responseJson()));

        self::assertSame(
            [
                'id',
                'title',
                'lessonTitle',
                'stage',
                'dueDate',
                'done',
                'completedAt',
                'completedByYou',
                'completedBy',
                'instructions',
                'resources',
            ],
            array_keys(JsonBody::asArray($this->responseJson()['task'] ?? null, 'task')),
        );

        $this->assertJsonPath('lesson', [
            'title' => 'The Good Samaritan',
            'teachingDate' => '2026-11-15',
            'passage' => 'Luke 10:25-37',
            'memoryVerse' => null,
        ]);

        $this->assertJsonPath('child', ['id' => $mina->id()->toRfc4122(), 'name' => 'Mina']);
        $this->assertJsonPath('task.completedBy', 'Hoda Farag');
        $this->assertJsonPath('task.completedByYou', false);
        $content = (string) $this->browser()->getResponse()->getContent();
        self::assertStringNotContainsString('Mina was sad', $content);
        self::assertStringNotContainsString('Love your neighbour', $content);

        $connection = new Database(self::getContainer())->connection();
        $connection->executeStatement('DELETE FROM people.guardian_relationship WHERE user_id = ?', [$hoda]);
        $connection->executeStatement('DELETE FROM identity.app_user WHERE id = ?', [$hoda]);

        $this->jsonRequest('GET', $this->detail($mina, $task));
        $this->assertJsonPath('task.completedBy', null);
        $this->assertJsonPath('task.done', true);
    }

    public function testStaffWithoutTheParentRoleAreRefused(): void
    {
        $this->signInWithRoles('servant@example.org', 'servant');
        $this->jsonRequest('GET', '/api/parent/children/' . Uuid::v7()->toRfc4122() . '/homework');
        $this->assertProblem(403, 'forbidden');
    }

    /**
     * @return list<string>
     */
    private function titles(): array
    {
        $items = JsonBody::asArray($this->responseJson()['items'] ?? null, 'items');

        return array_values(array_map(static fn (mixed $item): string => Type::of(
            JsonBody::asArray($item, 'item')['title'] ?? null,
        )->toString(), $items));
    }

    private function list(?Person $child): string
    {
        return '/api/parent/children/' . ($child?->id()->toRfc4122() ?? Uuid::v7()->toRfc4122()) . '/homework';
    }

    private function detail(Person $child, ?HomeworkAssignment $task): string
    {
        return $this->list($child) . '/' . ($task?->id()->toRfc4122() ?? Uuid::v7()->toRfc4122());
    }

    private function fixture(): ParentFixture
    {
        return new ParentFixture(self::getContainer());
    }
}
