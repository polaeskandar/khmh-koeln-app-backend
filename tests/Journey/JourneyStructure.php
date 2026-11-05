<?php

declare(strict_types=1);

namespace App\Tests\Journey;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class JourneyStructure
{
    /**
     * @return array<string, mixed>
     */
    public static function schedule(Service $school, Stage $third): array
    {
        return [
            'roster' => [
                'serviceId' => $school->id()->toRfc4122(),
                'stageId' => $third->id()->toRfc4122(),
                'mode' => 'expected',
            ],
            'details' => ['name' => 'Sunday class', 'location' => 'Hall'],
            'recurrence' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => [7],
                'span' => ['startsOn' => '2026-11-15'],
            ],
            'times' => ['startTime' => '12:00', 'endTime' => '13:15'],
        ];
    }

    /**
     * @return array{Service, Stage, Stage}
     */
    public static function sundaySchool(ContainerInterface $container): array
    {
        $school = Structure::service();
        $third = Structure::stage($school);
        $first = Structure::stage($school, '1st Class', 1);
        new Database($container)->persisted($school, $third, $first);

        return [$school, $third, $first];
    }
}
