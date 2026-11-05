<?php

declare(strict_types=1);

namespace App\Tests\Support\Interface\Api\Identity;

use App\Tests\Support\Context\Identity\Domain\Entity\MemberLinks;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class MemberFixture
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * @return array{child: string, youth: string, nevine: string}
     */
    public function people(): array
    {
        $school = Structure::service();
        $third = Structure::stage($school);
        $youthMeeting = Structure::service('Youth Meeting');
        $deacons = Structure::service('Deacons’ School');
        $advanced = Structure::stage($deacons, 'Advanced');
        $bible = Structure::service('Bible Study');
        new Database($this->container)->persisted($school, $third, $youthMeeting, $deacons, $advanced, $bible);
        $rosters = new Rosters($this->container);
        $child = $rosters->person('Mina', 'Hanna');
        $rosters->place($child, $school, $third, '2026-09-01');
        $youth = $rosters->person('Mina', 'Hanna');
        $rosters->place($youth, $youthMeeting, null, '2026-09-01');
        $rosters->place($youth, $deacons, $advanced, '2026-09-01');
        $nevine = $rosters->person('Nevine', 'Boulos');
        $rosters->place($nevine, $bible, null, '2026-09-01');
        $account = new SignInAccounts($this->container)->add('nevine.boulos@example.org');
        new MemberLinks($this->container)->link($account, $nevine->id()->toRfc4122());

        return [
            'child' => $child->id()->toRfc4122(),
            'youth' => $youth->id()->toRfc4122(),
            'nevine' => $nevine->id()->toRfc4122(),
        ];
    }
}
