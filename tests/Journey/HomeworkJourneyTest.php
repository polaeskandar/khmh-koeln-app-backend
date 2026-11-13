<?php

declare(strict_types=1);

namespace App\Tests\Journey;

use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Person;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Access\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Context\Scheduling\Domain\Entity\Schedules;
use App\Tests\Support\Interface\Api\Learning\ProgressRows;
use App\Tests\Support\JsonBody;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class HomeworkJourneyTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string HODA = 'hoda@example.org';

    private const string DANIEL = 'daniel@example.org';

    private const string SAMIR = 'samir@example.org';

    public function testAServantsHomeworkReachesTheParentAndTheReportComesBack(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        [$school, $third, $first] = JourneyStructure::sundaySchool(self::getContainer());
        new Schedules(self::getContainer())->weekly($school, 'Sunday class', 7, stage: $third);
        $rosters = new Rosters(self::getContainer());
        $mimi = $rosters->person('Mimi', 'Gerges');
        $rosters->place($mimi, $school, $first, '2026-09-01');

        $mina = $rosters->person('Mina', 'Hanna');
        $kiro = $rosters->person('Kiro', 'Nashed');

        foreach ([$mina, $kiro, $rosters->person('Abanoub', 'Rizk'), $rosters->person('Bishoy', 'Zaki')] as $child) {
            $rosters->place($child, $school, $third, '2026-09-01');
        }

        $this->createHodasAccount($mina, $mimi);
        $daniel = new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(self::DANIEL, 'servant'));
        new Families(self::getContainer())->link($this->signInWithRoles(self::SAMIR, 'parent'), $kiro);
        $task = $this->prepareHomework($third, $daniel);

        $this->assertHiddenBeforePublishing($mina, $task);
        $this->signInAs(self::DANIEL, SignInAccounts::TYPED);
        $this->jsonRequest('POST', '/api/homework/' . $task . '/publish', []);
        self::assertResponseIsSuccessful();

        $this->assertParentSeesAndReports($mina, $mimi, $task);
        $this->assertProgress($task, $mina, 'Hoda Farag');
        $this->assertOtherFamilyIsRefused($kiro, $mina, $task);
        $this->assertProgress($task, $mina, 'Hoda Farag');

        $this->signInAs(self::HODA, SignInAccounts::TYPED);
        $this->jsonRequest('DELETE', $this->childPath($mina) . '/' . $task . '/completion', []);
        self::assertResponseStatusCodeSame(204);
        $this->assertProgress($task, $mina, null);
    }

    private function createHodasAccount(Person $mina, Person $mimi): void
    {
        $hoda = new SignInAccounts(self::getContainer())->add(self::HODA, ['display_name' => 'Hoda Farag']);
        new RoleGrants(self::getContainer())->grant($hoda, 'parent');
        $families = new Families(self::getContainer());
        $families->link($hoda, $mina);
        $families->link($hoda, $mimi);
        $this->signInAs(self::HODA, SignInAccounts::TYPED);
    }

    private function prepareHomework(Stage $third, string $daniel): string
    {
        $this->signInAs(self::DANIEL, SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/stages/' . $third->id()->toRfc4122() . '/lesson-options');
        $options = JsonBody::asArray($this->responseJson()['options'] ?? null, 'options');
        $sunday = JsonBody::asArray($options[5] ?? null, 'option');

        $this->jsonRequest('POST', '/api/stages/' . $third->id()->toRfc4122() . '/lessons', [
            'details' => [
                'title' => 'The Good Samaritan',
                'passage' => 'Luke 10:25-37',
                'memoryVerse' => 'Love your neighbour as yourself.',
            ],
            'meeting' => ['scheduleId' => $sunday['scheduleId'] ?? null, 'date' => '2026-11-15'],
            'plan' => ['notes' => 'Mina was upset last week'],
            'responsibleId' => $daniel,
        ]);

        self::assertResponseStatusCodeSame(201);
        $lesson = Type::of($this->responseJson()['id'] ?? null)->toString();

        $this->jsonRequest('POST', '/api/lessons/' . $lesson . '/homework', [
            'title' => 'Learn the memory verse',
            'instructions' => 'Say it to your parents.',
            'dueDate' => '2026-11-22',
        ]);

        self::assertResponseStatusCodeSame(201);

        return Type::of($this->responseJson()['id'] ?? null)->toString();
    }

    private function assertHiddenBeforePublishing(Person $mina, string $task): void
    {
        $this->signInAs(self::HODA, SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/parent/children');
        $this->assertJsonPath('items.1.name', 'Mina');
        $this->assertJsonPath('items.1.toDo', 0);
        $this->jsonRequest('GET', $this->childPath($mina));
        $this->assertJsonPath('items', []);
        $this->jsonRequest('GET', $this->childPath($mina) . '/' . $task);
        $this->assertProblem(404, 'homework.notAvailable');
    }

    private function assertParentSeesAndReports(Person $mina, Person $mimi, string $task): void
    {
        $this->signInAs(self::HODA, SignInAccounts::TYPED);
        $this->jsonRequest('GET', $this->childPath($mina));
        $this->assertJsonPath('items.0.id', $task);
        $this->jsonRequest('GET', $this->childPath($mimi));
        $this->assertJsonPath('items', []);

        $this->jsonRequest('GET', $this->childPath($mina) . '/' . $task);
        $this->assertJsonPath('lesson.passage', 'Luke 10:25-37');
        $this->assertJsonPath('lesson.memoryVerse', 'Love your neighbour as yourself.');
        self::assertStringNotContainsString('upset', (string) $this->browser()->getResponse()->getContent());

        $this->jsonRequest('PUT', $this->childPath($mina) . '/' . $task . '/completion', []);
        self::assertResponseStatusCodeSame(201);
    }

    private function assertOtherFamilyIsRefused(Person $kiro, Person $mina, string $task): void
    {
        $this->signInAs(self::SAMIR, SignInAccounts::TYPED);
        $this->jsonRequest('GET', $this->childPath($kiro));
        $this->assertJsonPath('items.0.id', $task);

        $this->jsonRequest('GET', '/api/parent/children/' . Uuid::v7()->toRfc4122() . '/homework');
        $random = (string) $this->browser()->getResponse()->getContent();

        foreach ([['GET', ''], ['GET', '/' . $task], ['PUT', '/' . $task . '/completion']] as [$method, $suffix]) {
            $this->jsonRequest($method, $this->childPath($mina) . $suffix, []);
            $this->assertProblem(404, 'notFound.person');
            self::assertSame($random, (string) $this->browser()->getResponse()->getContent());
        }
    }

    private function assertProgress(string $task, Person $mina, ?string $reporter): void
    {
        $this->signInAs(self::DANIEL, SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/homework/' . $task . '/progress');
        $this->assertJsonPath('counts.assigned', 4);
        $this->assertJsonPath('counts.completed', $reporter === null ? 0 : 1);

        $rows = array_map(static fn (mixed $row): array => JsonBody::asArray(
            $row,
            'row',
        ), array_values(JsonBody::asArray($this->responseJson()['rows'] ?? null, 'rows')));

        $row = ProgressRows::rowOf($rows, $mina);
        self::assertSame($reporter !== null, $row['done'] ?? null);
        self::assertSame($reporter, ProgressRows::reporterName($row));
    }

    private function childPath(Person $child): string
    {
        return '/api/parent/children/' . $child->id()->toRfc4122() . '/homework';
    }
}
