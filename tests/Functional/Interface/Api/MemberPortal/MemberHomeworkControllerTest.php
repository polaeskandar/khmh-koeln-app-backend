<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\MemberPortal;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Learning\Domain\Entity\CopticCourse;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\JsonBody;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class MemberHomeworkControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testTheMemberHasTheLevelsTaskToDoAndTheirOwnReportDone(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $course = CopticCourse::seeded(self::getContainer());
        $nevine = $course->member('nevine@example.org', 'Nevine');
        $course->report('greeting', $nevine, 'nevine@example.org');
        $this->signInAs('nevine@example.org', SignInAccounts::TYPED);

        $this->jsonRequest('GET', '/api/member/homework');
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('items.0.title', 'Write the first eight letters');
        $this->assertJsonPath('items.0.dueDate', '2026-11-09');
        self::assertCount(1, JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'));

        $this->jsonRequest('GET', '/api/member/homework?state=done');
        $this->assertJsonPath('items.0.title', 'Say the Easter greeting at home');
        $this->assertJsonPath('items.0.completedByYou', true);

        $this->jsonRequest('GET', '/api/member/homework?state=all');
        $this->assertFieldError('state', 'validation.invalidChoice', ['choices' => '"todo", "done"']);
    }

    public function testTheDetailHasExactlyTheContractsKeysAndAnythingHiddenIsTheSame404(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $course = CopticCourse::seeded(self::getContainer());
        $course->member('nevine@example.org', 'Nevine');
        $this->signInAs('nevine@example.org', SignInAccounts::TYPED);

        $this->jsonRequest('GET', '/api/member/homework/' . $course->task('letters')->id()->toRfc4122());
        self::assertResponseIsSuccessful();
        self::assertSame(['today', 'task', 'lesson'], array_keys($this->responseJson()));

        $this->assertJsonPath('task.resources', [[
            'label' => 'The Coptic alphabet (chart)',
            'url' => 'https://example.org/alphabet.pdf',
        ]]);

        $this->assertJsonPath('lesson.title', 'The first eight letters');
        $body = (string) $this->browser()->getResponse()->getContent();
        self::assertStringNotContainsString('objectives', $body);
        self::assertStringNotContainsString('notes', $body);

        $refusals = [];

        foreach (['words', 'samaritan', 'archived'] as $hidden) {
            $this->jsonRequest('GET', '/api/member/homework/' . $course->task($hidden)->id()->toRfc4122());
            $refusals[] = (string) $this->browser()->getResponse()->getContent();
        }

        $this->jsonRequest('GET', '/api/member/homework/' . Uuid::v7()->toRfc4122());
        $this->assertProblem(404, 'homework.notAvailable');

        self::assertSame(
            array_fill(start_index: 0, count: 3, value: (string) $this->browser()->getResponse()->getContent()),
            $refusals,
        );

        $this->jsonRequest('GET', '/api/member/homework/not-an-id');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAParentsReportForAnOlderChildIsNamedInTheChildsOwnList(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $course = CopticCourse::seeded(self::getContainer());
        $youssef = $course->member('youssef@example.org', 'Youssef');
        $families = new Families(self::getContainer());
        $families->link($families->account('hoda@example.org', ['display_name' => 'Hoda Farag']), $youssef);
        $course->report('greeting', $youssef, 'hoda@example.org');
        $this->signInAs('youssef@example.org', SignInAccounts::TYPED);

        $this->jsonRequest('GET', '/api/member/homework?state=done');

        $this->assertJsonPath('items.0.completedBy', 'Hoda Farag');
        $this->assertJsonPath('items.0.completedByYou', false);
    }
}
