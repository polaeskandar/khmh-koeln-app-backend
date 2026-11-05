<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use Doctrine\Common\Collections\ArrayCollection;

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
        private ChurchServices $services,
        private ServiceStages $stages,
    ) {
    }

    /**
     * @return list<Service|Stage>
     */
    public function seed(): array
    {
        $school = $this->services->create('Sunday School', 'Classes for children from Kindergarten to 6th Class.');
        $schoolId = $school->id()->toRfc4122();

        $stages = new ArrayCollection(self::SUNDAY_SCHOOL_STAGES)->map(fn (string $name): Stage => $this->stages->add(
            $schoolId,
            $name,
            description: null,
        ));

        return [
            ...$stages->getValues(),
            $this->services->create('Youth Meeting', 'The Friday evening meeting for young people.'),
        ];
    }
}
