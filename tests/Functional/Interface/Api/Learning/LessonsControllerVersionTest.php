<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Access\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Database;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class LessonsControllerVersionTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testASecondEditWithTheSameVersionIsRefusedAndTheFirstStays(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $lesson = new Lessons(self::getContainer())->inThirdClass();

        $daniel = new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'daniel@example.org',
            'servant',
        ));

        $this->prepares($lesson, $daniel);
        $this->jsonRequest('GET', $this->path($lesson));
        $this->assertJsonPath('lesson.version', 1);

        $this->jsonRequest('PUT', $this->path($lesson) . '?version=1', $this->body($lesson, $daniel, ['details' => [
            'title' => 'First edit',
        ]]));

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('version', 2);

        $this->jsonRequest('PUT', $this->path($lesson) . '?version=1', $this->body($lesson, $daniel, ['details' => [
            'title' => 'Second edit',
        ]]));

        $this->assertProblem(409, 'lessons.changedMeanwhile');
        $this->jsonRequest('GET', $this->path($lesson));
        $this->assertJsonPath('lesson.title', 'First edit');
        $this->assertJsonPath('lesson.version', 2);

        $this->jsonRequest('PUT', $this->path($lesson), $this->body($lesson, $daniel, ['details' => [
            'title' => 'Without a version',
        ]]));

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('version', 3);
    }

    public function testAStatusChangeAndAHelpersOnlyEditMoveTheVersion(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $lesson = new Lessons(self::getContainer())->inThirdClass();
        $helper = new SignInAccounts(self::getContainer())->add('christine@example.org');
        new RoleGrants(self::getContainer())->grant($helper, 'servant');
        new ServedGroups(self::getContainer())->everyService($helper);

        $daniel = new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'daniel@example.org',
            'servant',
        ));

        $this->prepares($lesson, $daniel);

        $this->jsonRequest('POST', $this->path($lesson) . '/status', ['status' => 'ready']);
        $this->assertJsonPath('version', 2);

        $this->jsonRequest('PUT', $this->path($lesson) . '?version=1', $this->body($lesson, $daniel, ['details' => [
            'title' => 'Stale',
        ]]));

        $this->assertProblem(409, 'lessons.changedMeanwhile');

        $this->jsonRequest('PUT', $this->path($lesson) . '?version=2', $this->body($lesson, $daniel, [
            'details' => ['collaboratorIds' => [$helper]],
        ]));

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('version', 3);

        $this->jsonRequest('PUT', $this->path($lesson) . '?version=2', $this->body($lesson, $daniel, [
            'details' => ['collaboratorIds' => []],
        ]));

        $this->assertProblem(409, 'lessons.changedMeanwhile');
        $this->jsonRequest('GET', $this->path($lesson));
        $this->assertJsonPath('lesson.collaborators.0.id', $helper);
    }

    public function testAVersionThatIsNoNumberIsRefused(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $lesson = new Lessons(self::getContainer())->inThirdClass();

        $daniel = new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'daniel@example.org',
            'servant',
        ));

        $this->prepares($lesson, $daniel);

        $this->jsonRequest('PUT', $this->path($lesson) . '?version=new', $this->body($lesson, $daniel, ['details' => [
            'title' => 'Edit',
        ]]));

        $this->assertProblem(422, 'invalidRequest');
    }

    private function prepares(Lesson $lesson, string $accountId): void
    {
        new Database(self::getContainer())
            ->connection()
            ->update('learning.lesson', ['responsible_id' => $accountId], ['id' => $lesson->id()->toRfc4122()]);
    }

    /**
     * @param array<string, mixed> $changes
     *
     * @return array<array-key, mixed>
     */
    private function body(Lesson $lesson, string $responsibleId, array $changes): array
    {
        $link = $lesson->link();

        return array_replace_recursive([
            'details' => ['title' => 'The Good Samaritan'],
            'meeting' => ['scheduleId' => $link->scheduleId?->toRfc4122(), 'date' => $link->teachingDate->toString()],
            'responsibleId' => $responsibleId,
        ], $changes);
    }

    private function path(Lesson $lesson): string
    {
        return '/api/lessons/' . $lesson->id()->toRfc4122();
    }
}
