<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use App\Tests\Support\Logs;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class LessonOptionsControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testTheSundaysOfTheWindowCarryTheirSessionStates(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$third, $class] = $this->thirdClass();
        $schedules = new Schedules(self::getContainer());

        $schedules->session(
            $class,
            '2026-11-01',
            "status = 'completed', opened_at = created_at, closed_at = created_at, title = 'Harvest', start_time = '10:00'",
        );

        $schedules->session($class, '2026-11-08', "status = 'active', opened_at = created_at");
        $schedules->session($class, '2026-11-22', "status = 'canceled', cancel_reason = 'Holiday'");
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('daniel@example.org', 'servant'));

        $this->jsonRequest('GET', $this->options($third));

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('today', '2026-11-08');
        $options = $this->dates();
        self::assertCount(17, $options);
        self::assertSame('2026-10-11 planned', $options[0] ?? null);
        self::assertSame('2027-01-31 planned', $options[16] ?? null);

        self::assertSame(
            ['2026-11-01 completed', '2026-11-08 active', '2026-11-15 planned', '2026-11-22 canceled'],
            array_slice($options, offset: 3, length: 4),
        );

        $this->assertJsonPath('options.3.title', 'Harvest');
        $this->assertJsonPath('options.3.startTime', '10:00');

        $this->assertJsonPath('options.5', [
            'scheduleId' => $class->id()->toRfc4122(),
            'date' => '2026-11-15',
            'startTime' => '18:30',
            'title' => 'Sunday class',
            'state' => 'planned',
            'takenBy' => null,
        ]);
    }

    public function testTakenDatesNameTheirLessonExceptTheOneEdited(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$third, $class] = $this->thirdClass();
        $lessons = new Lessons(self::getContainer());
        $samaritan = $lessons->stored($third, $class, '2026-11-15');
        $early = $lessons->stored($third, $class, '2026-09-27', 'Creation');

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'christine@example.org',
            'service_leader',
        ));

        $this->jsonRequest('GET', $this->options($third));

        $this->assertJsonPath('options.5.takenBy', [
            'id' => $samaritan->id()->toRfc4122(),
            'title' => 'The Good Samaritan',
        ]);

        $this->jsonRequest('GET', $this->options($third) . '?lessonId=' . $samaritan->id()->toRfc4122());
        $this->assertJsonPath('options.5.takenBy', null);

        $this->jsonRequest('GET', $this->options($third) . '?lessonId=' . $early->id()->toRfc4122());
        $this->assertJsonPath('options.0.date', '2026-09-27');
        $this->assertJsonPath('options.0.takenBy', null);
        $this->assertJsonPath('options.7.takenBy.title', 'The Good Samaritan');
        self::assertCount(19, $this->dates());
    }

    public function testServantsAreThePreparersAndMoreLessonsCostNoMoreStatements(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$third, $class] = $this->thirdClass();
        $lessons = new Lessons(self::getContainer());
        $lessons->stored($third, $class, '2026-11-15');
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('daniel@example.org', 'servant'));

        $this->jsonRequest('GET', $this->options($third));
        $few = count(new Logs(self::getContainer())->containing('SELECT'));
        self::assertSame(['Sara Hanna'], $this->servantNames());

        foreach (['2026-11-22', '2026-11-29', '2026-12-06', '2026-12-13'] as $date) {
            $lessons->stored($third, $class, $date);
        }

        $this->jsonRequest('GET', $this->options($third));

        self::assertCount($few, new Logs(self::getContainer())->containing('SELECT'));
    }

    public function testOtherStagesLessonsUnknownStagesAndAccess(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$third] = $this->thirdClass();
        $first = Structure::stage(Structure::service('Youth Meeting'), '1st Class');
        new Database(self::getContainer())->persisted($first->service(), $first);
        $other = new Lessons(self::getContainer())->stored($first, null, '2026-11-15');
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('daniel@example.org', 'servant'));

        $this->jsonRequest('GET', $this->options($third) . '?lessonId=' . $other->id()->toRfc4122());
        $this->assertProblem(404, 'notFound.lesson');

        foreach ([Uuid::v7()->toRfc4122(), 'not-a-uuid'] as $stageId) {
            $this->jsonRequest('GET', '/api/stages/' . $stageId . '/lesson-options');
            $this->assertProblem(404, 'notFound.stage');
        }

        $this->jsonRequest('GET', $this->options($third) . '?lessonId=nope');
        $this->assertFieldError('lessonId', 'validation.invalidType');

        $this->jsonRequest('GET', $this->options($third) . '?lessonId=');
        $this->assertFieldError('lessonId', 'validation.invalidType');

        $this->signInWithRoles('hoda@example.org', 'parent');
        $this->jsonRequest('GET', $this->options($third));
        $this->assertProblem(403, 'lessons.prepareStageOnly');
    }

    public function testAnonymousCallersAreUnauthenticated(): void
    {
        $this->jsonRequest('GET', '/api/stages/' . Uuid::v7()->toRfc4122() . '/lesson-options');
        $this->assertProblem(401, 'auth.unauthenticated');
    }

    private function options(Stage $stage): string
    {
        return '/api/stages/' . $stage->id()->toRfc4122() . '/lesson-options';
    }

    /**
     * @return list<string>
     */
    private function dates(): array
    {
        $options = JsonBody::asArray($this->responseJson()['options'] ?? null, 'options');

        return array_values(array_map(static function (mixed $raw): string {
            $option = JsonBody::asArray($raw, 'option');

            return Type::of($option['date'] ?? null)->toString() . ' ' . Type::of($option['state'] ?? null)->toString();
        }, $options));
    }

    /**
     * @return list<string>
     */
    private function servantNames(): array
    {
        $servants = JsonBody::asArray($this->responseJson()['servants'] ?? null, 'servants');

        return array_values(array_map(static fn (mixed $raw): string => Type::of(
            JsonBody::asArray($raw, 'servant')['name'] ?? null,
        )->toString(), $servants));
    }

    /**
     * @return array{Stage, Schedule}
     */
    private function thirdClass(): array
    {
        $service = Structure::service();
        $third = Structure::stage($service);
        $first = Structure::stage($service, '1st Class', 1);
        new Database(self::getContainer())->persisted($service, $third, $first);
        $schedules = new Schedules(self::getContainer());
        $class = $schedules->weekly($service, 'Sunday class', 7, stage: $third);
        $old = $schedules->weekly($service, 'Old class', 7, stage: $third);
        $schedules->weekly($service, 'First class', 7, stage: $first);

        new Database(self::getContainer())
            ->connection()
            ->update('meetings.meeting_schedule', ['active' => 'false'], ['id' => $old->id()->toRfc4122()]);

        return [$third, $class];
    }
}
