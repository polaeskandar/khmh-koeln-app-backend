<?php

declare(strict_types=1);

namespace App\Tests\Journey;

use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Identity\EmailedLinks;
use App\Tests\Support\Interface\Api\Identity\Registrations;
use App\Tests\Support\JsonBody;
use DateTimeImmutable;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class MemberJourneyTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string MINA = 'mina.hanna@example.org';

    private const string MARIAM = 'mariam@example.org';

    private const string PETER = 'peter@example.org';

    public function testAYouthGetsAnAccountOfHisOwnSeesHisGroupsAndReportsHisHomework(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        [$advanced, $saturday, $thirdTask, $youth] = $this->churchOfTwoMinas();
        $peter = $this->signInWithRoles(self::PETER, 'service_leader');
        $this->signInWithRoles(self::MARIAM, 'administrator');
        $this->registerAndApproveMina($youth);
        $task = $this->prepareAdvancedHomework($advanced, $saturday, $peter);

        $this->signInAs(self::MINA, SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/member/homework');
        $this->assertJsonPath('items', []);
        $this->jsonRequest('GET', '/api/member/homework/' . $task);
        $this->assertProblem(404, 'homework.notAvailable');

        $this->signInAs(self::PETER, SignInAccounts::TYPED);
        $this->jsonRequest('POST', '/api/homework/' . $task . '/publish', []);
        self::assertResponseIsSuccessful();

        $this->assertMinaSeesOnlyHisOwnGroups($task, $thirdTask);
        $this->jsonRequest('PUT', '/api/member/homework/' . $task . '/completion', []);
        self::assertResponseStatusCodeSame(201);
        $minaSession = $this->browser()->getCookieJar()->all();
        $this->browser()->getCookieJar()->clear();

        $this->signInAs(self::PETER, SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/homework/' . $task . '/progress');
        $this->assertJsonPath('rows.0.done', true);
        $this->assertJsonPath('rows.0.ownAccount', true);
        $this->assertJsonPath('rows.0.reportedBySelf', true);

        $this->removeMinasLink($minaSession);
    }

    /**
     * @return array{Stage, Schedule, string, string}
     */
    private function churchOfTwoMinas(): array
    {
        $school = Structure::service();
        $third = Structure::stage($school);
        $youthMeeting = Structure::service('Youth Meeting');
        $deacons = Structure::service('Deacons’ School');
        $advanced = Structure::stage($deacons, 'Advanced');
        new Database(self::getContainer())->persisted($school, $third, $youthMeeting, $deacons, $advanced);
        $schedules = new Schedules(self::getContainer());
        $sunday = $schedules->weekly($school, 'Sunday class', 7, stage: $third);
        $schedules->weekly($youthMeeting, 'Youth evening', 5);
        $saturday = $schedules->weekly($deacons, 'Advanced class', 6, stage: $advanced);
        $rosters = new Rosters(self::getContainer());
        $rosters->place($rosters->person('Mina', 'Hanna'), $school, $third, '2026-09-01');
        $youth = $rosters->person('Mina', 'Hanna');
        $rosters->place($youth, $youthMeeting, null, '2026-09-01');
        $rosters->place($youth, $deacons, $advanced, '2026-09-01');

        $task = new HomeworkTask(
            new Lessons(self::getContainer())->stored($third, $sunday, '2026-11-08'),
            HomeworkText::of('Tell the story at home', 'Tell it to your family.', null),
            Materials::none(),
            createdBy: null,
        );

        $task->changeState($task->state()->published(new DateTimeImmutable('2026-11-08T12:00:00+01:00')));
        new Database(self::getContainer())->persisted($task);

        return [$advanced, $saturday, $task->id()->toRfc4122(), $youth->id()->toRfc4122()];
    }

    private function registerAndApproveMina(string $youth): void
    {
        $this->jsonRequest('POST', '/api/auth/register', Registrations::parent([
            'account' => ['firstName' => 'Mina', 'lastName' => 'Hanna', 'email' => self::MINA],
            'registration' => ['registeredAs' => 'member', 'note' => 'Youth Meeting and Deacons’ School Advanced'],
        ]));

        self::assertResponseStatusCodeSame(202);

        $this->jsonRequest('POST', '/api/auth/email-confirmations', [
            'token' => new EmailedLinks(self::getContainer())->lastToken(),
        ]);

        $this->signInAs(self::MARIAM, SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/account-requests/member-candidates?q=mina%20han');
        self::assertCount(2, JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'));

        $this->jsonRequest('POST', '/api/account-requests/' . $this->minaAccount() . '/approve', [
            'as' => 'member',
            'member' => [
                'personId' => $youth,
                'ageCheck' => 'guardianConsent',
                'guardianConsentNote' => 'Father agreed.',
            ],
        ]);

        self::assertResponseIsSuccessful();
    }

    private function prepareAdvancedHomework(Stage $advanced, Schedule $saturday, string $peter): string
    {
        $this->signInAs(self::PETER, SignInAccounts::TYPED);

        $this->jsonRequest('POST', '/api/stages/' . $advanced->id()->toRfc4122() . '/lessons', [
            'details' => ['title' => 'The Trisagion', 'memoryVerse' => 'Holy God, Holy Mighty, Holy Immortal.'],
            'meeting' => ['scheduleId' => $saturday->id()->toRfc4122(), 'date' => '2026-11-14'],
            'responsibleId' => $peter,
        ]);

        self::assertResponseStatusCodeSame(201);
        $lesson = Type::of($this->responseJson()['id'] ?? null)->toString();

        $this->jsonRequest('POST', '/api/lessons/' . $lesson . '/homework', [
            'title' => 'Practise the Trisagion',
            'instructions' => 'Sing it once a day.',
            'dueDate' => '2026-11-21',
        ]);

        self::assertResponseStatusCodeSame(201);

        return Type::of($this->responseJson()['id'] ?? null)->toString();
    }

    private function assertMinaSeesOnlyHisOwnGroups(string $task, string $thirdTask): void
    {
        $this->signInAs(self::MINA, SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/member/homework');
        $this->assertJsonPath('items.0.id', $task);
        $this->jsonRequest('GET', '/api/member/groups');
        $this->assertJsonPath('items.0.stage.name', 'Advanced');
        $this->assertJsonPath('items.1.service.name', 'Youth Meeting');
        $this->jsonRequest('GET', '/api/member/meetings');
        self::assertStringNotContainsString('Sunday class', (string) $this->browser()->getResponse()->getContent());

        $this->jsonRequest('GET', '/api/member/homework/' . Uuid::v7()->toRfc4122());
        $random = (string) $this->browser()->getResponse()->getContent();
        $this->jsonRequest('GET', '/api/member/homework/' . $thirdTask);
        self::assertSame($random, (string) $this->browser()->getResponse()->getContent());
    }

    /**
     * @param array<array-key, Cookie> $minaSession
     */
    private function removeMinasLink(array $minaSession): void
    {
        $this->restore($minaSession);
        $this->jsonRequest('GET', '/api/member/homework');
        self::assertResponseIsSuccessful();
        $this->browser()->getCookieJar()->clear();

        $this->signInAs(self::MARIAM, SignInAccounts::TYPED);
        $this->jsonRequest('POST', '/api/member-accounts/' . $this->minaAccount() . '/unlink', []);
        self::assertResponseStatusCodeSame(204);
        $this->jsonRequest('GET', '/api/account-requests');
        $this->assertJsonPath('items.0.account.email', self::MINA);

        $this->restore($minaSession);
        $this->jsonRequest('GET', '/api/member/homework');
        $this->assertProblem(401, 'auth.unauthenticated');
    }

    /**
     * @param array<array-key, Cookie> $cookies
     */
    private function restore(array $cookies): void
    {
        $jar = $this->browser()->getCookieJar();
        $jar->clear();

        foreach ($cookies as $cookie) {
            $jar->set($cookie);
        }
    }

    private function minaAccount(): string
    {
        return Type::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne('SELECT id::text FROM identity.app_user WHERE email = ?', [self::MINA]),
        )->toString();
    }
}
