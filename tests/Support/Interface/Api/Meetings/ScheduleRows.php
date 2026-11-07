<?php

declare(strict_types=1);

namespace App\Tests\Support\Interface\Api\Meetings;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class ScheduleRows
{
    private const string PLAN = "concat_ws('|', title, location, start_time, end_time, roster_mode)";

    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * @return array{Service, Stage}
     */
    public function structure(): array
    {
        $service = Structure::service();
        $stage = Structure::stage($service);
        new Database($this->container)->persisted($service, $stage);

        return [$service, $stage];
    }

    public function column(string $id, string $column): mixed
    {
        return $this->connection()->fetchOne('SELECT "' . $column . '" FROM meetings.meeting_schedule WHERE id = ?', [
            $id,
        ]);
    }

    public function sessionOn(string $id, string $date): mixed
    {
        return $this->connection()->fetchOne('SELECT '
        . self::PLAN
        . ' FROM meetings.meeting_session WHERE schedule_id = ? AND occurrence_date = ?', [$id, $date]);
    }

    public function openSessionsOf(string $id): void
    {
        $this->connection()->executeStatement("UPDATE meetings.meeting_session SET status = 'active', opened_at = created_at WHERE schedule_id = ?", [
            $id,
        ]);
    }

    public function deactivate(string $id): void
    {
        $this->connection()->update('meetings.meeting_schedule', ['active' => 'false'], ['id' => $id]);
    }

    public function archive(string $table, Uuid $id): void
    {
        $this->connection()->update($table, ['archived_at' => '2026-11-01 12:00:00+01'], ['id' => $id->toRfc4122()]);
    }

    private function connection(): Connection
    {
        return new Database($this->container)->connection();
    }
}
