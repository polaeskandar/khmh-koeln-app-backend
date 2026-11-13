<?php

declare(strict_types=1);

namespace App\Tests\Functional\Workflow\Privacy;

use App\Library\Problem\Exception\NotFound;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\Scheduling\Domain\Entity\Schedules;
use App\Tests\Support\Database;
use App\Workflow\Privacy\EraseAccountWorkflow;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class EraseAccountWorkflowTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testTheErasedAccountsSessionEndsAndItsHistoryStaysWithoutIt(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $session = $this->session();

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'leader@example.org',
            'service_leader',
        ));

        $servant = new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'servant@example.org',
            'servant',
        ));

        $this->jsonRequest('POST', '/api/sessions/' . $session . '/open', []);
        self::assertResponseIsSuccessful();

        $this->workflow()($servant);

        $this->jsonRequest('GET', '/api/me');
        $this->assertProblem(401, 'auth.unauthenticated');

        $this->signInAs('leader@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/sessions/' . $session);
        $this->assertJsonPath('history.0.type', 'opened');
        $this->assertJsonPath('history.0.actor', null);
        $this->assertJsonPath('history.0.byGenerator', false);
    }

    public function testARerunForgetsWhatALateRequestWroteForTheErasedAccount(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $session = $this->session();

        $servant = new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'servant@example.org',
            'servant',
        ));

        $this->workflow()($servant);
        $connection = new Database(self::getContainer())->connection();

        $connection->insert('scheduling.session_event', [
            'id' => Uuid::v7()->toRfc4122(),
            'session_id' => $session,
            'type' => 'opened',
            'occurred_at' => '2026-11-08 10:01:00+01',
            'actor_id' => $servant,
            'by_generator' => 'false',
        ]);

        try {
            $this->workflow()($servant);
            self::fail('A second erasure must report the account as gone.');
        } catch (NotFound $gone) {
            self::assertSame('notFound.account', $gone->getMessage());
        }

        self::assertSame(0, $connection->fetchOne('SELECT count(*) FROM scheduling.session_event WHERE actor_id = ?', [
            $servant,
        ]));
    }

    private function session(): string
    {
        $service = Structure::service();
        new Database(self::getContainer())->persisted($service);
        $schedules = new Schedules(self::getContainer());

        return $schedules
            ->session($schedules->weekly($service, 'Sunday class', 7), '2026-11-08')
            ->id()
            ->toRfc4122();
    }

    private function workflow(): EraseAccountWorkflow
    {
        $workflow = self::getContainer()->get(EraseAccountWorkflow::class);
        self::assertInstanceOf(EraseAccountWorkflow::class, $workflow);

        return $workflow;
    }
}
