<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Privacy;

use App\Context\People\Domain\Entity\Person;
use App\Context\Privacy\Domain\Enum\RequestKind;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Privacy\ErasureFixture;
use App\Tests\Support\Logs;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class DataRequestsControllerErasureTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string NOTES = <<<'SQL'
        SELECT (SELECT count(cancel_reason) FROM meetings.meeting_session)
             + (SELECT count(removal_reason) FROM meetings.attendance)
             + (SELECT count(notes) FROM learning.lesson)
        SQL;

    private const string KIRO = "SELECT count(*) FROM people.person WHERE first_name = 'Kiro'";

    public function testTheReviewShowsWhatGoesWhatStaysAndTheNotesThatNameTheMember(): void
    {
        [$fixture, $id] = $this->erasureForKiro();

        $this->jsonRequest('GET', '/api/data-requests/' . $id . '/erasure');

        self::assertResponseIsSuccessful();

        $this->assertJsonPath('erase', [
            'person' => 1,
            'memberships' => 1,
            'attendance' => 1,
            'completions' => 0,
            'guardianLinks' => 1,
            'account' => 0,
            'roles' => 0,
        ]);

        $this->assertJsonPath('keep', ['sessionTotals' => 1, 'accountRows' => 0]);
        $this->assertJsonPath('mentions.0.where', 'removalReason');
        $this->assertJsonPath('mentions.1.where', 'cancelReason');
        $this->assertJsonPath('mentions.2.where', 'lessonNote');
        $this->assertJsonPath('mentions.2.text', 'Ask Kiro to read.');
        $this->assertJsonPath('mentions.2.date', '2026-11-15');
        $this->assertJsonPath('mentions.2.group', '3rd Class');
        self::assertSame(1, $fixture->numberOf(self::KIRO));
    }

    public function testClearingTheNotesEmptiesOnlyTheMentions(): void
    {
        [$fixture, $id] = $this->erasureForKiro();

        $this->jsonRequest('POST', '/api/data-requests/' . $id . '/erase', ['clearNotes' => true]);

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('status', 'done');
        $this->assertJsonPath('outcome.notes', 3);
        $this->assertJsonPath('outcome.person', 1);
        $this->assertJsonPath('subjectName', null);
        $this->assertJsonPath('erasedSubject', ['kind' => 'member', 'group' => '3rd Class']);
        self::assertSame(2, $fixture->numberOf(self::NOTES));
        self::assertSame(0, $fixture->numberOf(self::KIRO));
    }

    public function testKeepingTheNotesLeavesThem(): void
    {
        [$fixture, $id] = $this->erasureForKiro();

        $this->jsonRequest('POST', '/api/data-requests/' . $id . '/erase', ['clearNotes' => false]);

        $this->assertJsonPath('outcome.notes', 0);
        self::assertSame(5, $fixture->numberOf(self::NOTES));

        self::assertSame(
            1,
            $fixture->numberOf("SELECT count(*) FROM learning.lesson WHERE notes = 'Ask Kiro to read.'"),
        );
    }

    public function testAfterwardsNoRequestHoldsTheMembersName(): void
    {
        [$fixture, $id, $kiro] = $this->erasureForKiro();
        $copy = $fixture->request(RequestKind::Copy, $kiro);

        $this->jsonRequest('POST', '/api/data-requests/' . $id . '/erase', ['clearNotes' => true]);

        self::assertSame(
            0,
            $fixture->numberOf(
                "SELECT count(*) FROM privacy.data_request r WHERE to_jsonb(r)::text ~* 'kiro|nashed' OR person_id IS NOT NULL",
            ),
        );

        $this->jsonRequest('GET', '/api/data-requests/' . $copy);
        $this->assertJsonPath('request.subjectName', null);
        $this->assertJsonPath('request.erasedSubject', ['kind' => 'member', 'group' => '3rd Class']);
        $this->assertJsonPath('request.status', 'open');

        $this->jsonRequest('GET', '/api/data-requests/' . $copy . '/erasure');
        $this->assertProblem(409, 'privacy.notErasure');
    }

    public function testAFailureMidwayChangesNothing(): void
    {
        [$fixture, $id] = $this->erasureForKiro();
        $connection = new Database(self::getContainer())->connection();

        $connection->executeStatement(<<<'SQL'
            CREATE FUNCTION people.refuse_delete() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'refused'; END $$
            SQL);

        $connection->executeStatement(
            'CREATE TRIGGER refuse_delete BEFORE DELETE ON people.person FOR EACH ROW EXECUTE FUNCTION people.refuse_delete()',
        );

        $this->jsonRequest('POST', '/api/data-requests/' . $id . '/erase', ['clearNotes' => true]);

        self::assertResponseStatusCodeSame(500);
        self::assertSame(5, $fixture->numberOf(self::NOTES));

        self::assertSame(
            1,
            $fixture->numberOf(
                "SELECT count(*) FROM privacy.data_request WHERE status = 'open' AND person_id IS NOT NULL",
            ),
        );

        self::assertSame(2, $fixture->numberOf('SELECT count(*) FROM people.membership'));
        self::assertSame(1, $fixture->numberOf(self::KIRO));
    }

    public function testTheReviewAndTheErasureLockTheRequestBeforeThePerson(): void
    {
        [, $id] = $this->erasureForKiro();

        $this->jsonRequest('GET', '/api/data-requests/' . $id . '/erasure');
        self::assertResponseIsSuccessful();
        $this->assertRequestLockedFirst();

        $this->jsonRequest('POST', '/api/data-requests/' . $id . '/erase', ['clearNotes' => false]);
        self::assertResponseIsSuccessful();
        $this->assertRequestLockedFirst();
    }

    /**
     * @return array{ErasureFixture, string, Person}
     */
    private function erasureForKiro(): array
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $fixture = new ErasureFixture(self::getContainer());
        [$kiro] = $fixture->kiro();
        $this->signInWithRoles('admin@example.org', 'administrator');

        return [$fixture, $fixture->request(RequestKind::Erasure, $kiro), $kiro];
    }

    private function assertRequestLockedFirst(): void
    {
        $locks = new Logs(self::getContainer())->containing('FOR UPDATE');

        $lastOf = static fn (string $table): int => max([
            -1,
            ...array_keys(array_filter($locks, static fn (string $lock): bool => str_contains($lock, $table))),
        ]);

        self::assertGreaterThan(-1, $lastOf('privacy.data_request'));
        self::assertGreaterThan($lastOf('privacy.data_request'), $lastOf('people.person'));
    }
}
