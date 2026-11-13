<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Organization;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Access\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use Symfony\Component\Uid\Uuid;

final class ServiceServantsControllerTest extends ApiTestCase
{
    public function testALeaderSeesWhoServesInHisServiceAndWhomHeMayAssign(): void
    {
        [$sundaySchool, $first, $third, $youth] = $this->structure();
        $sunday = $sundaySchool->id()->toRfc4122();
        $leader = $this->staff('Adel Fahmy', 'service_leader');
        $groups = new ServedGroups(self::getContainer());
        $leading = $groups->assign($leader, $sunday);
        $zeina = $this->staff('Zeina Gerges', 'servant');
        $zeinaInThird = $groups->assign($zeina, $sunday, $third->id()->toRfc4122());
        $groups->assign($zeina, $youth->id()->toRfc4122());
        $bishoy = $this->staff('Bishoy Adel', 'servant');
        $bishoyInFirst = $groups->assign($bishoy, $sunday, $first->id()->toRfc4122());
        $bishoyInThird = $groups->assign($bishoy, $sunday, $third->id()->toRfc4122());
        $marina = $this->staff('Marina Tawfik', 'servant');
        $youssef = $this->staff('Youssef Nashed', 'service_leader');
        $groups->assign($youssef, $youth->id()->toRfc4122());
        $groups->assign($this->staff('Hany Wahba', 'administrator'), $sunday);
        $groups->assign($this->staff('Irene Wahba', 'servant', ['disabled_at' => '2026-11-01 12:00:00+01']), $sunday);
        $this->staff('Mariam Saad', 'parent');
        $this->signInAs('adel.fahmy@example.org', SignInAccounts::TYPED);

        $this->jsonRequest('GET', '/api/services/' . $sunday . '/servants');

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('@', (string) $this->browser()->getResponse()->getContent());

        self::assertSame(
            [
                'servants' => [
                    [
                        'assignmentId' => $leading,
                        'account' => ['id' => $leader, 'name' => 'Adel Fahmy'],
                        'stage' => null,
                    ],
                    [
                        'assignmentId' => $bishoyInFirst,
                        'account' => ['id' => $bishoy, 'name' => 'Bishoy Adel'],
                        'stage' => $this->stageRef($first),
                    ],
                    [
                        'assignmentId' => $bishoyInThird,
                        'account' => ['id' => $bishoy, 'name' => 'Bishoy Adel'],
                        'stage' => $this->stageRef($third),
                    ],
                    [
                        'assignmentId' => $zeinaInThird,
                        'account' => ['id' => $zeina, 'name' => 'Zeina Gerges'],
                        'stage' => $this->stageRef($third),
                    ],
                ],
                'assignable' => [
                    ['id' => $leader, 'name' => 'Adel Fahmy'],
                    ['id' => $bishoy, 'name' => 'Bishoy Adel'],
                    ['id' => $marina, 'name' => 'Marina Tawfik'],
                    ['id' => $youssef, 'name' => 'Youssef Nashed'],
                    ['id' => $zeina, 'name' => 'Zeina Gerges'],
                ],
            ],
            $this->responseJson(),
        );
    }

    public function testAServantOfAStageMayNotListItsServiceAndDoesNotFindOthers(): void
    {
        [$sundaySchool, , $third, $youth] = $this->structure();
        $sunday = $sundaySchool->id()->toRfc4122();
        $path = '/api/services/' . $sunday . '/servants';

        $this->jsonRequest('GET', $path);
        $this->assertProblem(401, 'auth.unauthenticated');

        $servant = $this->signInWithRoles('servant@example.org', 'servant');
        new ServedGroups(self::getContainer())->assign($servant, $sunday, $third->id()->toRfc4122());

        $this->jsonRequest('GET', $path);
        $this->assertProblem(403, 'assignments.notAllowed');
        $this->jsonRequest('GET', '/api/services/' . $youth->id()->toRfc4122() . '/servants');
        $this->assertProblem(404, 'notFound.service');
        $this->jsonRequest('GET', '/api/services/' . Uuid::v7()->toRfc4122() . '/servants');
        $this->assertProblem(404, 'notFound.service');
        $this->jsonRequest('GET', '/api/services/not-an-id/servants');
        $this->assertProblem(404, 'notFound.service');

        $this->browser()->getCookieJar()->clear();
        $stageLeader = $this->signInWithRoles('leader@example.org', 'service_leader');
        new ServedGroups(self::getContainer())->assign($stageLeader, $sunday, $third->id()->toRfc4122());
        $this->jsonRequest('GET', $path);
        $this->assertProblem(403, 'assignments.notAllowed');

        $this->browser()->getCookieJar()->clear();
        $this->signInWithRoles('parent@example.org', 'parent');
        $this->jsonRequest('GET', $path);
        $this->assertProblem(403, 'forbidden');
    }

    public function testAnAdministratorListsTheServantsOfAnyService(): void
    {
        [$sundaySchool, , , $youth] = $this->structure();
        $youssef = $this->staff('Youssef Nashed', 'service_leader');
        $groups = new ServedGroups(self::getContainer());
        $leading = $groups->assign($youssef, $youth->id()->toRfc4122());
        $groups->assign($this->staff('Zeina Gerges', 'servant'), $sundaySchool->id()->toRfc4122());
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('GET', '/api/services/' . $youth->id()->toRfc4122() . '/servants');

        self::assertResponseIsSuccessful();

        $this->assertJsonPath('servants', [
            ['assignmentId' => $leading, 'account' => ['id' => $youssef, 'name' => 'Youssef Nashed'], 'stage' => null],
        ]);

        $this->assertJsonPath('assignable.0.name', 'Youssef Nashed');
        $this->assertJsonPath('assignable.1.name', 'Zeina Gerges');
    }

    /**
     * @return array{Service, Stage, Stage, Service}
     */
    private function structure(): array
    {
        $sundaySchool = Structure::service('Sunday School');
        $first = Structure::stage($sundaySchool, '1st Class', position: 0);
        $third = Structure::stage($sundaySchool, '3rd Class', position: 2);
        $youth = Structure::service('Youth Meeting');
        new Database(self::getContainer())->persisted($sundaySchool, $first, $third, $youth);

        return [$sundaySchool, $first, $third, $youth];
    }

    /**
     * @param array<string, string|null> $columns
     */
    private function staff(string $name, string $role, array $columns = []): string
    {
        $email = strtolower(strtr($name, from: ' ', to: '.')) . '@example.org';
        $id = new SignInAccounts(self::getContainer())->add($email, ['display_name' => $name, ...$columns]);
        new RoleGrants(self::getContainer())->grant($id, $role);

        return $id;
    }

    /**
     * @return array{id: string, name: string, position: int, archived: bool}
     */
    private function stageRef(Stage $stage): array
    {
        return [
            'id' => $stage->id()->toRfc4122(),
            'name' => $stage->name()->value,
            'position' => $stage->position(),
            'archived' => false,
        ];
    }
}
