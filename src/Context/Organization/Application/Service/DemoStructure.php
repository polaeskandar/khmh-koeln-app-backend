<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Context\Organization\Domain\Repository\StageRepositoryInterface;

final readonly class DemoStructure
{
    private const array SUNDAY_SCHOOL_STAGES = [
        'Kindergarten',
        '1st Class',
        '2nd Class',
        '3rd Class',
        '4th Class',
        '5th & 6th Class',
    ];

    public function __construct(
        private ServiceRepositoryInterface $services,
        private StageRepositoryInterface $stages,
    ) {
    }

    /**
     * @return list<Service|Stage>
     */
    public function seed(): array
    {
        $school = $this->service('Sunday School', 'Classes for children from Kindergarten to 6th Class.');
        $groups = [];

        foreach (self::SUNDAY_SCHOOL_STAGES as $position => $name) {
            $stage = new Stage($school, $name, description: null, position: $position);
            $this->stages->save($stage);
            $groups[] = $stage;
        }

        return [...$groups, ...$this->otherGroups()];
    }

    /**
     * @return list<Service|Stage>
     */
    private function otherGroups(): array
    {
        return [
            $this->service('Youth Meeting', 'The Friday evening meeting for young people.'),
            $this->stageOf('Deacons’ School', 'Hymns and the service of the altar.', 'Advanced'),
            $this->service('Bible Study', 'Wednesday evenings for adults.'),
            $this->stageOf('Coptic Language Course', 'Reading and writing Coptic.', 'Level 1'),
        ];
    }

    private function stageOf(string $service, string $description, string $stage): Stage
    {
        $level = new Stage($this->service($service, $description), $stage, description: null, position: 0);
        $this->stages->save($level);

        return $level;
    }

    private function service(string $name, string $description): Service
    {
        $service = new Service($name, $description);
        $this->services->save($service);

        return $service;
    }
}
