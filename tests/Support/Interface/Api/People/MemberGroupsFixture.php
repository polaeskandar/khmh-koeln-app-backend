<?php

declare(strict_types=1);

namespace App\Tests\Support\Interface\Api\People;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Person;
use App\Tests\Support\Context\Identity\Domain\Entity\MemberLinks;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Context\Scheduling\Domain\Entity\Schedules;
use App\Tests\Support\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class MemberGroupsFixture
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function nevine(): Person
    {
        $bible = Structure::service('Bible Study');
        $coptic = Structure::service('Coptic Language Course');
        $level1 = Structure::stage($coptic, 'Level 1');
        new Database($this->container)->persisted($bible, $coptic, $level1);
        $schedules = new Schedules($this->container);
        $evening = $schedules->weekly($bible, 'Bible Study evening', 3);
        $schedules->session($evening, '2026-11-11', "status = 'canceled', cancel_reason = 'Retreat weekend'");
        $class = $schedules->weekly($coptic, 'Level 1 class', 1, stage: $level1);
        $schedules->weekly($coptic, 'Level 1 summer reading', 4, endsOn: '2026-10-29', stage: $level1);

        new Database($this->container)
            ->connection()
            ->update(
                'scheduling.schedule',
                ['start_time' => '18:00', 'end_time' => '19:00'],
                ['id' => $class->id()->toRfc4122()],
            );

        $lessons = new Lessons($this->container);
        $taught = $lessons->stored($level1, $class, '2026-11-02', 'The first eight letters', 'Psalm 1');
        $lessons->stored($level1, null, '2026-11-08', 'A draft for today');

        new Database($this->container)
            ->connection()
            ->update(
                'learning.lesson',
                ['status' => 'ready', 'memory_verse' => 'Christ is risen.'],
                ['id' => $taught->id()->toRfc4122()],
            );

        $this->servant('irene@example.org', 'Irene Wahba', 'service_leader', $bible, null);
        $this->servant('youssef@example.org', 'Youssef Nashed', 'servant', $coptic, $level1);

        return $this->member('nevine@example.org', 'Nevine', 'Boulos', [[$bible, null], [$coptic, $level1]]);
    }

    /**
     * @param list<array{Service, Stage|null}> $groups
     */
    public function member(string $email, string $firstName, string $lastName, array $groups): Person
    {
        $rosters = new Rosters($this->container);
        $person = $rosters->person($firstName, $lastName);

        foreach ($groups as [$service, $stage]) {
            $rosters->place($person, $service, $stage, '2026-09-01');
        }

        $account = new SignInAccounts($this->container)->add($email);
        new MemberLinks($this->container)->link($account, $person->id()->toRfc4122());

        return $person;
    }

    private function servant(string $email, string $name, string $role, Service $service, ?Stage $stage): void
    {
        $id = new SignInAccounts($this->container)->add($email, ['display_name' => $name]);
        new RoleGrants($this->container)->grant($id, $role);
        new ServedGroups($this->container)->assign($id, $service->id()->toRfc4122(), $stage?->id()->toRfc4122());
    }
}
