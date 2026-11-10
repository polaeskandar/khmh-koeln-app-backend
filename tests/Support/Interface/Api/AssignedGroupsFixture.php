<?php

declare(strict_types=1);

namespace App\Tests\Support\Interface\Api;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class AssignedGroupsFixture
{
    public const string TODAY = '2026-11-08';

    public const string SERVANT = 'servant@example.org';

    public const string LEADER = 'leader@example.org';

    public const string UNASSIGNED = 'unassigned@example.org';

    public const string ADMIN = 'admin@example.org';

    public Service $sundaySchool;

    public Stage $first;

    public Stage $third;

    public Service $youth;

    public AssignedMeetings $meetings;

    public AssignedPeople $people;

    public function __construct(
        private ContainerInterface $container,
    ) {
        $this->sundaySchool = Structure::service('Sunday School');
        $this->first = Structure::stage($this->sundaySchool, '1st Class', position: 0);
        $this->third = Structure::stage($this->sundaySchool, '3rd Class', position: 1);
        $this->youth = Structure::service('Youth Meeting');
        new Database($container)->persisted($this->sundaySchool, $this->first, $this->third, $this->youth);

        $this->meetings = new AssignedMeetings(
            $container,
            $this->sundaySchool,
            $this->first,
            $this->third,
            $this->youth,
        );

        $this->people = new AssignedPeople($container, $this->sundaySchool, $this->first, $this->third, $this->youth);

        $this->staff(self::SERVANT, 'servant', $this->sundaySchool, $this->third);
        $this->staff(self::LEADER, 'service_leader', $this->sundaySchool, null);
        $this->staff(self::UNASSIGNED, 'servant', null, null);
        $this->staff(self::ADMIN, 'administrator', null, null);
    }

    private function staff(string $email, string $role, ?Service $service, ?Stage $stage): void
    {
        $id = new SignInAccounts($this->container)->add($email);
        new RoleGrants($this->container)->grant($id, $role);

        if ($service === null) {
            return;
        }

        new ServedGroups($this->container)->assign($id, $service->id()->toRfc4122(), $stage?->id()->toRfc4122());
    }
}
