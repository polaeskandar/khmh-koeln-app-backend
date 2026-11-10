<?php

declare(strict_types=1);

namespace App\Tests\Support\Interface\Api;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Person;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class AssignedPeople
{
    public Person $mina;

    public Person $sara;

    public Person $peter;

    public Person $mark;

    public function __construct(
        ContainerInterface $container,
        Service $sundaySchool,
        Stage $first,
        Stage $third,
        Service $youth,
    ) {
        $rosters = new Rosters($container);
        $this->mina = $rosters->person('Mina', 'Hanna');
        $this->sara = $rosters->person('Sara', 'Girgis');
        $this->peter = $rosters->person('Peter', 'Adel');
        $this->mark = $rosters->person('Mark', 'Wassef');
        $rosters->place($this->mina, $sundaySchool, $third, '2026-09-01');
        $rosters->place($this->sara, $sundaySchool, $first, '2026-09-01');
        $rosters->place($this->peter, $youth, null, '2026-09-01');
    }
}
