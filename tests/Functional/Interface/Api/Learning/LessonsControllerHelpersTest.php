<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\Scheduling\Domain\Entity\Schedules;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Learning\LessonRequests;
use App\Tests\Support\Logs;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class LessonsControllerHelpersTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testHelpersAreDeduplicatedWithoutTheResponsibleAndShownInListAndDetail(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$third, $class] = $this->thirdClass();
        $christine = $this->servant('christine@example.org', 'Christine Adel');

        $daniel = new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'daniel@example.org',
            'servant',
        ));

        $this->jsonRequest('POST', '/api/stages/' . $third->id()->toRfc4122() . '/lessons', [
            'details' => ['title' => 'The Good Samaritan', 'collaboratorIds' => [$christine, $christine, $daniel]],
            'meeting' => ['scheduleId' => $class->id()->toRfc4122(), 'date' => '2026-11-15'],
            'responsibleId' => $daniel,
        ]);

        self::assertResponseStatusCodeSame(201);
        $id = Type::of($this->responseJson()['id'] ?? null)->toString();
        $this->jsonRequest('GET', '/api/lessons/' . $id);
        $this->assertJsonPath('lesson.collaborators', [['id' => $christine, 'name' => 'Christine Adel']]);
        $this->jsonRequest('GET', '/api/stages/' . $third->id()->toRfc4122() . '/lessons?tab=upcoming');
        $this->assertJsonPath('items.0.collaborators', [['id' => $christine, 'name' => 'Christine Adel']]);
    }

    public function testIdsInCapitalsCountAsTheSameAccountAndTheReplacementLocksTheLesson(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$third, $class] = $this->thirdClass();
        $christine = $this->servant('christine@example.org', 'Christine Adel');

        $daniel = new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'daniel@example.org',
            'servant',
        ));

        $this->jsonRequest('POST', '/api/stages/' . $third->id()->toRfc4122() . '/lessons', [
            'details' => [
                'title' => 'The Good Samaritan',
                'collaboratorIds' => [$christine, strtoupper($christine), strtoupper($daniel)],
            ],
            'meeting' => ['scheduleId' => $class->id()->toRfc4122(), 'date' => '2026-11-15'],
            'responsibleId' => $daniel,
        ]);

        self::assertResponseStatusCodeSame(201);
        $id = Type::of($this->responseJson()['id'] ?? null)->toString();

        $this->jsonRequest('PUT', '/api/lessons/' . $id, LessonRequests::body($class, '2026-11-15', $daniel, [
            'details' => ['collaboratorIds' => [strtoupper($christine)]],
        ]));

        self::assertResponseIsSuccessful();
        $lines = new Logs(self::getContainer())->containing('');

        $firstOf = static fn (string $needle): int => min([
            \PHP_INT_MAX,
            ...array_keys(array_filter($lines, static fn (string $line): bool => str_contains($line, $needle))),
        ]);

        self::assertLessThan(
            $firstOf('DELETE FROM learning.lesson_collaborator'),
            $firstOf('FROM learning.lesson WHERE id = ? FOR UPDATE'),
        );

        $this->jsonRequest('GET', '/api/lessons/' . $id);
        $this->assertJsonPath('lesson.collaborators', [['id' => $christine, 'name' => 'Christine Adel']]);
    }

    public function testOnlyPreparersBecomeHelpersKeptOnesStayAndLeavingTheListOutClearsIt(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$third, $class] = $this->thirdClass();
        $christine = $this->servant('christine@example.org', 'Christine Adel');
        $mina = $this->servant('mina@example.org', 'Mina Wassef');
        $parent = new SignInAccounts(self::getContainer())->add('hoda@example.org');

        $daniel = new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'daniel@example.org',
            'servant',
        ));

        $lesson = new Lessons(self::getContainer())->stored($third, $class, '2026-11-15');

        new Database(self::getContainer())
            ->connection()
            ->update('learning.lesson', ['responsible_id' => $daniel], ['id' => $lesson->id()->toRfc4122()]);

        $path = '/api/lessons/' . $lesson->id()->toRfc4122();

        $helpers = static fn (array $ids): array => LessonRequests::body($class, '2026-11-15', $daniel, [
            'details' => ['collaboratorIds' => $ids],
        ]);

        $this->jsonRequest('PUT', $path, $helpers([$parent]));
        $this->assertFieldError('details.collaboratorIds', 'lessons.helpersInStage');

        $this->jsonRequest('PUT', $path, $helpers([$christine, $mina]));
        self::assertResponseIsSuccessful();

        new Database(self::getContainer())
            ->connection()
            ->executeStatement('DELETE FROM identity.user_role WHERE user_id = ?', [$christine]);

        $this->jsonRequest('PUT', $path, $helpers([$christine]));
        self::assertResponseIsSuccessful();
        $this->jsonRequest('GET', $path);
        $this->assertJsonPath('lesson.collaborators', [['id' => $christine, 'name' => 'Christine Adel']]);
        $this->jsonRequest('PUT', $path, LessonRequests::body($class, '2026-11-15', $daniel));
        self::assertResponseIsSuccessful();
        $this->jsonRequest('GET', $path);
        $this->assertJsonPath('lesson.collaborators', []);
    }

    public function testTheListCostsNoMoreStatementsForMoreLessons(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$third, $class] = $this->thirdClass();
        $christine = $this->servant('christine@example.org', 'Christine Adel');
        $lessons = new Lessons(self::getContainer());
        $this->helped($lessons->stored($third, $class, '2026-11-15')->id()->toRfc4122(), $christine);
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('daniel@example.org', 'servant'));
        $path = '/api/stages/' . $third->id()->toRfc4122() . '/lessons?tab=upcoming';
        $this->jsonRequest('GET', $path);
        $few = \count(new Logs(self::getContainer())->containing('SELECT'));

        foreach (range(start: 1, end: 24) as $week) {
            $date = new \DateTimeImmutable('2026-11-15')
                ->modify('+' . $week . ' weeks')
                ->format('Y-m-d');

            $this->helped(
                $lessons
                    ->stored($third, $class, $date, 'Lesson ' . $week)
                    ->id()
                    ->toRfc4122(),
                $christine,
            );
        }

        $this->jsonRequest('GET', $path);
        self::assertCount($few, new Logs(self::getContainer())->containing('SELECT'));
    }

    private function servant(string $email, string $name): string
    {
        $id = new SignInAccounts(self::getContainer())->add($email, ['display_name' => $name]);
        new RoleGrants(self::getContainer())->grant($id, 'servant');
        new ServedGroups(self::getContainer())->everyService($id);

        return $id;
    }

    private function helped(string $lessonId, string $accountId): void
    {
        new Database(self::getContainer())
            ->connection()
            ->insert('learning.lesson_collaborator', ['lesson_id' => $lessonId, 'user_id' => $accountId]);
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
