<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Privacy\Domain\Entity;

use App\Tests\Support\Database;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DataRequestTest extends KernelTestCase
{
    /**
     * @param array<string, string|null> $columns
     */
    #[DataProvider('impossibleRequests')]
    public function testTheDatabaseRefusesImpossibleRequests(array $columns): void
    {
        $sqlState = null;

        try {
            $this->insert($columns);
        } catch (DriverException $refused) {
            $sqlState = $refused->getSQLState();
        }

        self::assertSame('23514', $sqlState);
    }

    /**
     * @return iterable<string, array{array<string, string|null>}>
     */
    public static function impossibleRequests(): iterable
    {
        yield 'unknown kind' => [['kind' => 'delete']];
        yield 'unknown channel' => [['channel' => 'fax']];
        yield 'unknown status' => [['status' => 'paused']];
        yield 'unknown erased kind' => [['erased_subject_kind' => 'ghost']];
        yield 'open but closed' => [['closed_at' => '2026-11-03 12:00:00+01']];
        yield 'done but not closed' => [['status' => 'done']];
        yield 'declined without reason' => [['status' => 'declined', 'closed_at' => '2026-11-03 12:00:00+01']];
        yield 'outcome on a copy' => [[
            'status' => 'done',
            'closed_at' => '2026-11-03 12:00:00+01',
            'outcome' => '{"person": 1}',
        ]];

        yield 'no subject' => [['person_id' => null]];
        yield 'long note' => [['note' => str_repeat('a', times: 2001)]];
    }

    public function testAnAccountHasAtMostOneOpenErasureRequestFromTheApp(): void
    {
        $app = ['kind' => 'erasure', 'person_id' => null, 'app_user_id' => Uuid::v7()->toRfc4122(), 'channel' => 'app'];
        $this->insert($app);
        $this->insert([...$app, 'channel' => 'email']);
        $this->insert([...$app, 'status' => 'done', 'closed_at' => '2026-11-03 12:00:00+01', 'outcome' => '{}']);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insert($app);
    }

    /**
     * @param array<string, string|null> $columns
     */
    private function insert(array $columns): void
    {
        new Database(self::getContainer())
            ->connection()
            ->insert('privacy.data_request', [
                'id' => Uuid::v7()->toRfc4122(),
                'kind' => 'copy',
                'person_id' => Uuid::v7()->toRfc4122(),
                'requested_by' => 'Hoda Farag',
                'channel' => 'email',
                'received_at' => '2026-11-02 12:00:00+01',
                'due_on' => '2026-12-02',
                'status' => 'open',
                'created_at' => '2026-11-02 12:00:00+01',
                ...$columns,
            ]);
    }
}
