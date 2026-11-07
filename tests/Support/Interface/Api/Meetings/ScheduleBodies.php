<?php

declare(strict_types=1);

namespace App\Tests\Support\Interface\Api\Meetings;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;

final readonly class ScheduleBodies
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function created(Service $service, ?Stage $stage, string $startsOn): array
    {
        $roster = [
            'serviceId' => $service->id()->toRfc4122(),
            'stageId' => $stage?->id()->toRfc4122(),
            'mode' => 'expected',
        ];

        return ['roster' => $roster, ...self::plan($startsOn)];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    public static function replaced(array $overrides = []): array
    {
        return array_replace_recursive([...self::plan('2026-11-08'), 'rosterMode' => 'expected'], $overrides);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function plan(string $startsOn): array
    {
        return [
            'details' => ['name' => '  Sunday   class ', 'description' => '', 'location' => 'Hall'],
            'recurrence' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => [7],
                'span' => ['startsOn' => $startsOn],
            ],
            'times' => ['startTime' => '12:00', 'endTime' => '13:30'],
        ];
    }
}
