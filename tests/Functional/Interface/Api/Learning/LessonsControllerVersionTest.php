<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Database;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class LessonsControllerVersionTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testASecondEditWithTheSameVersionIsRefusedAndTheFirstStays(): void
    {
        $lesson = new Lessons(self::getContainer())->inThirdClass();
        $this->prepares($lesson, $this->signInWithRoles('daniel@example.org', 'servant'));
        $this->jsonRequest('GET', $this->path($lesson));
        $this->assertJsonPath('lesson.version', 1);

        $this->jsonRequest('PATCH', $this->path($lesson) . '?version=1', ['details' => ['title' => 'First edit']]);
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('version', 2);

        $this->jsonRequest('PATCH', $this->path($lesson) . '?version=1', ['details' => ['title' => 'Second edit']]);
        $this->assertProblem(409, 'lessons.changedMeanwhile');
        $this->jsonRequest('GET', $this->path($lesson));
        $this->assertJsonPath('lesson.title', 'First edit');
        $this->assertJsonPath('lesson.version', 2);

        $this->jsonRequest('PATCH', $this->path($lesson), ['details' => ['title' => 'Without a version']]);
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('version', 3);
    }

    public function testAStatusChangeAndAHelpersOnlyEditMoveTheVersion(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $lesson = new Lessons(self::getContainer())->inThirdClass();
        $helper = new SignInAccounts(self::getContainer())->add('christine@example.org');
        new RoleGrants(self::getContainer())->grant($helper, 'servant');
        $this->prepares($lesson, $this->signInWithRoles('daniel@example.org', 'servant'));

        $this->jsonRequest('POST', $this->path($lesson) . '/status', ['status' => 'ready']);
        $this->assertJsonPath('version', 2);
        $this->jsonRequest('PATCH', $this->path($lesson) . '?version=1', ['details' => ['title' => 'Stale']]);
        $this->assertProblem(409, 'lessons.changedMeanwhile');

        $this->jsonRequest('PATCH', $this->path($lesson) . '?version=2', [
            'details' => ['collaboratorIds' => [$helper]],
        ]);

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('version', 3);
        $this->jsonRequest('PATCH', $this->path($lesson) . '?version=2', ['details' => ['collaboratorIds' => []]]);
        $this->assertProblem(409, 'lessons.changedMeanwhile');
        $this->jsonRequest('GET', $this->path($lesson));
        $this->assertJsonPath('lesson.collaborators.0.id', $helper);
    }

    public function testAVersionThatIsNoNumberIsRefused(): void
    {
        $lesson = new Lessons(self::getContainer())->inThirdClass();
        $this->prepares($lesson, $this->signInWithRoles('daniel@example.org', 'servant'));

        $this->jsonRequest('PATCH', $this->path($lesson) . '?version=new', ['details' => ['title' => 'Edit']]);

        $this->assertProblem(422, 'invalidRequest');
    }

    private function prepares(Lesson $lesson, string $accountId): void
    {
        new Database(self::getContainer())
            ->connection()
            ->update('learning.lesson', ['responsible_id' => $accountId], ['id' => $lesson->id()->toRfc4122()]);
    }

    private function path(Lesson $lesson): string
    {
        return '/api/lessons/' . $lesson->id()->toRfc4122();
    }
}
