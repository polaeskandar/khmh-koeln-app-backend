<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Learning\ProgressFixture;
use App\Tests\Support\Interface\Api\Learning\ProgressRows;
use App\Tests\Support\JsonBody;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class HomeworkControllerProgressTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testEveryChildIsListedAlphabeticallyWithWhoReportedIt(): void
    {
        self::mockTime('2026-11-08T12:30:00+01:00');
        [$task, $mina, $hoda] = new ProgressFixture(self::getContainer())->create();
        $this->signInWithRoles('daniel@example.org', 'servant');

        $this->jsonRequest('GET', $this->path($task));

        self::assertResponseIsSuccessful();
        $rows = $this->rows();
        self::assertCount(15, $rows);
        self::assertSame(['Adel', 'Ägid', 'Bishoy'], array_slice(ProgressRows::lastNames($rows), offset: 0, length: 3));

        $minaRow = ProgressRows::rowOf($rows, $mina);
        self::assertTrue($minaRow['done'] ?? null);
        self::assertTrue($minaRow['inStage'] ?? null);
        self::assertSame('2026-11-08T12:08:00+01:00', $minaRow['completedAt'] ?? null);
        self::assertSame(['id' => $hoda, 'name' => 'Hoda Farag'], $minaRow['completedBy'] ?? null);
        self::assertCount(2, JsonBody::asArray($minaRow['parentAccounts'] ?? null, 'parentAccounts'));

        $former = array_values(array_filter(
            $rows,
            static fn (array $row): bool => ($row['inStage'] ?? null) === false,
        ));

        self::assertCount(1, $former);
        $this->assertJsonPath('counts.assigned', 14);
        $this->assertJsonPath('counts.reportedTotal', 2);
        $this->assertJsonPath('homework.instructions', 'Read it twice.');
    }

    public function testAGoneReporterIsNullAndAnUndoneReportIsNotDone(): void
    {
        self::mockTime('2026-11-08T12:30:00+01:00');
        [$task, $mina, $hoda] = new ProgressFixture(self::getContainer())->create();
        $connection = new Database(self::getContainer())->connection();
        $connection->executeStatement('DELETE FROM people.guardian_relationship WHERE user_id = ?', [$hoda]);
        $connection->executeStatement('DELETE FROM identity.app_user WHERE id = ?', [$hoda]);
        $this->signInWithRoles('daniel@example.org', 'servant');

        $this->jsonRequest('GET', $this->path($task));

        $minaRow = ProgressRows::rowOf($this->rows(), $mina);
        self::assertSame('2026-11-08T12:08:00+01:00', $minaRow['completedAt'] ?? null);
        self::assertArrayHasKey('completedBy', $minaRow);
        self::assertNull($minaRow['completedBy']);

        $connection->executeStatement("UPDATE learning.homework_completion SET undone_at = '2026-11-08 12:20:00+01'");
        $this->jsonRequest('GET', $this->path($task));

        $minaRow = ProgressRows::rowOf($this->rows(), $mina);
        self::assertFalse($minaRow['done'] ?? null);
        self::assertArrayHasKey('completedAt', $minaRow);
        self::assertNull($minaRow['completedAt']);
    }

    private function path(HomeworkTask $task): string
    {
        return '/api/homework/' . $task->id()->toRfc4122() . '/progress';
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function rows(): array
    {
        $rows = JsonBody::asArray($this->responseJson()['rows'] ?? null, 'rows');

        return array_values(array_map(static fn (mixed $row): array => JsonBody::asArray($row, 'row'), $rows));
    }
}
