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

        $groups[] = $this->service('Youth Meeting', 'The Friday evening meeting for young people.');

        return $groups;
    }

    private function service(string $name, string $description): Service
    {
        $service = new Service($name, $description);
        $this->services->save($service);

        return $service;
    }
}
