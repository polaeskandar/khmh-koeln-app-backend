<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Privacy\Domain\Entity;

use App\Context\Privacy\Domain\Enum\RequestKind;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Privacy\CopyLinks;
use App\Tests\Support\Interface\Api\Privacy\ErasureFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class DataRequestLifecycleTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string NOW = '2026-11-08 10:00:00+01';

    private const string CLOSED_AT = '2026-11-03 12:00:00+01';

    private const string VIEWER = 'viewer';

    private const string OPEN = '';

    private const string DONE = "status = 'done', closed_at = '" . self::CLOSED_AT . "'";

    private const string WITHDRAWN = "status = 'withdrawn', closed_at = '" . self::CLOSED_AT . "'";

    private const string DECLINED =
        "status = 'declined', closed_at = '" . self::CLOSED_AT . "', decline_reason = 'Not the person'";

    private const string ROW = <<<'SQL'
        SELECT status, closed_at::text AS closed_at, closed_by::text AS closed_by, decline_reason,
               (outcome IS NOT NULL)::text AS outcome
        FROM privacy.data_request WHERE id = ?
        SQL;

    private const string EMAIL = 'hoda@example.org';

    /**
     * @param array{RequestKind, string} $request
     * @param array<string, ?string> $row
     */
    #[DataProvider('staffActions')]
    public function testEveryStateMeetsEveryStaffAction(
        array $request,
        string $action,
        int $status,
        ?string $answer,
        array $row,
    ): void {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $id = $this->request(...$request);
        $viewer = $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', '/api/data-requests/' . $id . '/' . $action, ['clearNotes' => false]);

        self::assertResponseStatusCodeSame($status);
        self::assertSame($answer, $this->answer());
        self::assertSame($row, $this->stored($id, $viewer));
    }

    /**
     * @return iterable<string, array{array{RequestKind, string}, string, int, ?string, array<string, ?string>}>
     */
    public static function staffActions(): iterable
    {
        $erased = [
            ...self::openRow(),
            'status' => 'done',
            'closed_at' => self::NOW,
            'closed_by' => self::VIEWER,
            'outcome' => 'true',
        ];

        yield 'open erasure, erase' => [[RequestKind::Erasure, self::OPEN], 'erase', 200, 'done', $erased];
        yield 'done erasure, erase' => [
            [RequestKind::Erasure, self::DONE],
            'erase',
            409,
            'privacy.notOpen',
            self::doneRow(),
        ];

        yield 'withdrawn erasure, erase' => [
            [RequestKind::Erasure, self::WITHDRAWN],
            'erase',
            409,
            'privacy.notOpen',
            self::withdrawnRow(),
        ];

        yield 'declined erasure, erase' => [
            [RequestKind::Erasure, self::DECLINED],
            'erase',
            409,
            'privacy.notOpen',
            self::declinedRow(),
        ];

        yield 'open copy, erase' => [
            [RequestKind::Copy, self::OPEN],
            'erase',
            409,
            'privacy.notErasure',
            self::openRow(),
        ];

        yield 'done copy, erase' => [[RequestKind::Copy, self::DONE], 'erase', 409, 'privacy.notOpen', self::doneRow()];
        yield 'open copy, copy link' => [[RequestKind::Copy, self::OPEN], 'copy-link', 201, null, self::openRow()];

        yield 'done copy, copy link' => [
            [RequestKind::Copy, self::DONE],
            'copy-link',
            409,
            'privacy.notOpen',
            self::doneRow(),
        ];

        yield 'withdrawn copy, copy link' => [
            [RequestKind::Copy, self::WITHDRAWN],
            'copy-link',
            409,
            'privacy.notOpen',
            self::withdrawnRow(),
        ];

        yield 'declined copy, copy link' => [
            [RequestKind::Copy, self::DECLINED],
            'copy-link',
            409,
            'privacy.notOpen',
            self::declinedRow(),
        ];

        yield 'open erasure, copy link' => [
            [RequestKind::Erasure, self::OPEN],
            'copy-link',
            409,
            'privacy.notCopy',
            self::openRow(),
        ];
    }

    public function testUsingTheCopyLinkCompletesTheRequestOnce(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $id = $this->request(RequestKind::Copy, self::OPEN);
        $viewer = $this->signInWithRoles('admin@example.org', 'administrator');
        $this->jsonRequest('POST', '/api/data-requests/' . $id . '/copy-link');
        $token = CopyLinks::tokenOf(Type::of($this->responseJson()['url'] ?? null)->toString());

        $this->jsonRequest('POST', '/api/data-copies', ['token' => $token]);
        self::assertResponseIsSuccessful();

        self::assertSame(
            [...self::openRow(), 'status' => 'done', 'closed_at' => self::NOW],
            $this->stored($id, $viewer),
        );

        $this->jsonRequest('POST', '/api/data-copies', ['token' => $token]);
        $this->assertProblem(422, 'privacy.copyLinkInvalid');
        $this->jsonRequest('POST', '/api/data-requests/' . $id . '/copy-link');
        $this->assertProblem(409, 'privacy.notOpen');
    }

    public function testOnlyAnOpenScheduledErasureIsWithdrawnAndOnlyOnce(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $this->signInWithRoles(self::EMAIL, 'parent');
        $this->jsonRequest('POST', '/api/me/erasure-request', ['password' => SignInAccounts::TYPED]);
        self::assertResponseStatusCodeSame(201);
        $id = $this->scheduledErasure();
        $this->browser()->getCookieJar()->clear();

        $this->withdraw();
        self::assertResponseStatusCodeSame(204);

        self::assertSame(
            [...self::openRow(), 'status' => 'withdrawn', 'closed_at' => self::NOW],
            $this->stored($id, ''),
        );

        $this->withdraw();
        $this->assertProblem(409, 'privacy.nothingScheduled');
    }

    public function testAScheduledErasureThatIsDoneIsNotWithdrawn(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $this->signInWithRoles(self::EMAIL, 'parent');
        $this->jsonRequest('POST', '/api/me/erasure-request', ['password' => SignInAccounts::TYPED]);
        $id = $this->scheduledErasure();
        $this->update($id, self::DONE);
        $this->browser()->getCookieJar()->clear();

        $this->withdraw();
        $this->assertProblem(409, 'privacy.nothingScheduled');
        self::assertSame(self::doneRow(), $this->stored($id, ''));
    }

    /**
     * @return array<string, ?string>
     */
    private static function openRow(): array
    {
        return [
            'status' => 'open',
            'closed_at' => null,
            'closed_by' => null,
            'decline_reason' => null,
            'outcome' => 'false',
        ];
    }

    /**
     * @return array<string, ?string>
     */
    private static function doneRow(): array
    {
        return [...self::openRow(), 'status' => 'done', 'closed_at' => self::CLOSED_AT];
    }

    /**
     * @return array<string, ?string>
     */
    private static function withdrawnRow(): array
    {
        return [...self::openRow(), 'status' => 'withdrawn', 'closed_at' => self::CLOSED_AT];
    }

    /**
     * @return array<string, ?string>
     */
    private static function declinedRow(): array
    {
        return [
            ...self::openRow(),
            'status' => 'declined',
            'closed_at' => self::CLOSED_AT,
            'decline_reason' => 'Not the person',
        ];
    }

    private function request(RequestKind $kind, string $state): string
    {
        $id = new ErasureFixture(self::getContainer())->request($kind, new Rosters(self::getContainer())->person(
            'Mina',
            'Hanna',
        ));

        if ($state !== self::OPEN) {
            $this->update($id, $state);
        }

        return $id;
    }

    private function update(string $id, string $set): void
    {
        new Database(self::getContainer())
            ->connection()
            ->executeStatement('UPDATE privacy.data_request SET ' . $set . ' WHERE id = ?', [$id]);
    }

    private function scheduledErasure(): string
    {
        return Type::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne("SELECT id::text FROM privacy.data_request WHERE channel = 'app' AND erases_on IS NOT NULL"),
        )->toString();
    }

    private function withdraw(): void
    {
        $this->jsonRequest('POST', '/api/auth/erasure-cancellation', [
            'email' => self::EMAIL,
            'password' => SignInAccounts::TYPED,
        ]);
    }

    private function answer(): ?string
    {
        $body = $this->responseJson();

        return Type::of($body['code'] ?? $body['status'] ?? null)->toStringOrNull();
    }

    /**
     * @return array<string, ?string>
     */
    private function stored(string $id, string $viewer): array
    {
        $row = new Database(self::getContainer())
            ->connection()
            ->fetchAssociative(self::ROW, [$id]);

        $values = array_map(
            static fn (mixed $value): ?string => Type::of($value)->toStringOrNull(),
            $row === false ? [] : $row,
        );

        $closedBy = $values['closed_by'] ?? null;

        return [...$values, 'closed_by' => $closedBy === $viewer ? self::VIEWER : $closedBy];
    }
}
