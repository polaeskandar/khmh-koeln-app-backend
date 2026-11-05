<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Privacy;

use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Workflow\Privacy\EraseAccountWorkflow;
use App\Workflow\Privacy\ErasePersonWorkflow;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class DataRequestsControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testOnlyAdministratorsMayUseTheLog(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $body = $this->body(Uuid::v7()->toRfc4122(), '2026-11-02');

        foreach (['service_leader', 'servant'] as $role) {
            $this->signInWithRoles($role . '@example.org', $role);

            foreach ([
                ['POST', '/api/data-requests', $body],
                ['GET', '/api/data-requests', []],
                ['GET', '/api/data-requests/' . Uuid::v7()->toRfc4122(), []],
                ['POST', '/api/data-requests/' . Uuid::v7()->toRfc4122() . '/erase', ['clearNotes' => false]],
                ['GET', '/api/data-requests/' . Uuid::v7()->toRfc4122() . '/erasure', []],
            ] as [$method, $path, $sent]) {
                $this->jsonRequest($method, $path, $sent);
                $this->assertProblem(403, 'privacy.adminOnly');
            }
        }
    }

    public function testAnErasedSubjectLeavesTheRequestWithWhatItWas(): void
    {
        self::mockTime('2026-11-08T10:15:00+01:00');

        $mina = new Rosters(self::getContainer())
            ->person('Mina', 'Hanna')
            ->id()
            ->toRfc4122();

        $hoda = new Families(self::getContainer())->account('hoda@example.org');
        $this->signInWithRoles('admin@example.org', 'administrator');
        $this->jsonRequest('POST', '/api/data-requests', $this->body($mina, '2026-11-02'));
        $aboutMina = Type::of($this->responseJson()['id'] ?? null)->toString();

        $this->jsonRequest('POST', '/api/data-requests', [
            ...$this->body($mina, '2026-11-02'),
            'subject' => ['accountId' => $hoda],
        ]);

        $aboutHoda = Type::of($this->responseJson()['id'] ?? null)->toString();

        $erasePerson = self::getContainer()->get(ErasePersonWorkflow::class);
        $eraseAccount = self::getContainer()->get(EraseAccountWorkflow::class);
        self::assertInstanceOf(ErasePersonWorkflow::class, $erasePerson);
        self::assertInstanceOf(EraseAccountWorkflow::class, $eraseAccount);
        $erasePerson($mina);
        $eraseAccount($hoda);

        $this->jsonRequest('GET', '/api/data-requests/' . $aboutMina);
        $this->assertJsonPath('request.subjectName', null);
        $this->assertJsonPath('request.erasedSubject', ['kind' => 'member', 'group' => null]);

        $this->jsonRequest('GET', '/api/data-requests/' . $aboutHoda);
        $this->assertJsonPath('request.erasedSubject', ['kind' => 'account', 'group' => null]);
        $this->assertJsonPath('account', null);
    }

    /**
     * @param array<string, mixed> $changes
     * @param array<string, string|int> $params
     */
    #[DataProvider('invalidRequests')]
    public function testEachInvalidFieldHasItsCode(
        array $changes,
        string $field,
        string $code,
        array $params = [],
    ): void {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $mina = new Rosters(self::getContainer())->person('Mina', 'Hanna');
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest(
            'POST',
            '/api/data-requests',
            array_replace_recursive($this->body($mina->id()->toRfc4122(), '2026-11-02'), $changes),
        );

        $this->assertFieldError($field, $code, $params);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string, 2: string, 3?: array<string, string|int>}>
     */
    public static function invalidRequests(): iterable
    {
        yield 'long requester' => [
            ['receipt' => ['requestedBy' => str_repeat('a', times: 201)]],
            'receipt.requestedBy',
            'validation.tooLong',
            ['max' => 200, 'limit' => 200],
        ];

        yield 'blank requester' => [
            ['receipt' => ['requestedBy' => ' ']],
            'receipt.requestedBy',
            'privacy.requestedByRequired',
        ];

        yield 'long note' => [
            ['note' => str_repeat('a', times: 2001)],
            'note',
            'validation.tooLong',
            ['max' => 2000, 'limit' => 2000],
        ];

        yield 'impossible date' => [
            ['receipt' => ['receivedOn' => '2026-02-30']],
            'receipt.receivedOn',
            'validation.invalidDate',
        ];

        yield 'future date' => [
            ['receipt' => ['receivedOn' => '2026-11-09']],
            'receipt.receivedOn',
            'privacy.receivedInFuture',
        ];

        yield 'app channel' => [
            ['receipt' => ['channel' => 'app']],
            'receipt.channel',
            'validation.invalidChoice',
            ['choices' => '"email", "letter", "in_person"'],
        ];

        yield 'unknown subject' => [
            ['subject' => ['personId' => '019a0000-0000-7000-8000-000000000000']],
            'subject',
            'privacy.chooseSubject',
        ];

        yield 'malformed subject' => [
            ['subject' => ['personId' => 'mina']],
            'subject.personId',
            'validation.invalidType',
        ];
    }

    public function testARecordedRequestReadsBackWithItsReceiptAndDueDate(): void
    {
        self::mockTime('2026-11-08T10:15:00+01:00');
        $mina = new Rosters(self::getContainer())->person('Mina', 'Hanna');
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', '/api/data-requests', $this->body($mina->id()->toRfc4122(), '2026-11-02'));
        self::assertResponseStatusCodeSame(201);
        $earlier = Type::of($this->responseJson()['id'] ?? null)->toString();
        $this->jsonRequest('POST', '/api/data-requests', $this->body($mina->id()->toRfc4122(), '2026-11-08'));
        $today = Type::of($this->responseJson()['id'] ?? null)->toString();

        $this->jsonRequest('GET', '/api/data-requests/' . $earlier);
        $this->assertJsonPath('request.receivedAt', '2026-11-02T12:00:00+01:00');
        $this->assertJsonPath('request.dueOn', '2026-12-02');
        $this->assertJsonPath('request.subjectName', 'Mina Hanna');
        $this->assertJsonPath('member.firstName', 'Mina');
        $this->assertJsonPath('account', null);

        $this->jsonRequest('GET', '/api/data-requests/' . $today);
        $this->assertJsonPath('request.receivedAt', '2026-11-08T10:15:00+01:00');

        $this->jsonRequest('GET', '/api/data-requests');
        $this->assertJsonPath('open.0.id', $earlier);
        $this->assertJsonPath('closed', []);
        $this->assertJsonPath('today', '2026-11-08');

        new Database(self::getContainer())
            ->connection()
            ->executeStatement('DELETE FROM people.person');

        $this->jsonRequest('GET', '/api/data-requests/' . $earlier);
        $this->assertJsonPath('request.subjectName', null);
        $this->assertJsonPath('member', null);

        $this->jsonRequest('GET', '/api/data-requests/' . Uuid::v7()->toRfc4122());
        $this->assertProblem(404, 'notFound.request');

        $this->jsonRequest('GET', '/api/data-requests/not-a-request');
        $this->assertProblem(404, 'notFound.request');
    }

    /**
     * @return array<string, mixed>
     */
    private function body(string $personId, string $receivedOn): array
    {
        return [
            'kind' => 'copy',
            'subject' => ['personId' => $personId],
            'receipt' => ['requestedBy' => 'Hoda Farag', 'channel' => 'email', 'receivedOn' => $receivedOn],
            'note' => 'Asked by email for everything about Mina.',
        ];
    }
}
