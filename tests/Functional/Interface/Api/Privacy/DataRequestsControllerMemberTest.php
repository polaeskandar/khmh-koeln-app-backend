<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Privacy;

use App\Context\Privacy\Domain\Enum\RequestKind;
use App\Library\Types\Model\Integers;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\CopticCourse;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Privacy\ErasureFixture;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class DataRequestsControllerMemberTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testErasingAMembersRecordErasesTheirAccountInTheSameRequest(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $course = CopticCourse::seeded(self::getContainer());
        $nevine = $course->member('nevine@example.org', 'Nevine');
        $course->report('greeting', $nevine, 'nevine@example.org');
        $request = new ErasureFixture(self::getContainer())->request(RequestKind::Erasure, $nevine);
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('GET', '/api/data-requests/' . $request . '/erasure');
        $this->assertJsonPath('erase.account', 1);
        $this->jsonRequest('POST', '/api/data-requests/' . $request . '/erase', ['clearNotes' => false]);

        self::assertResponseIsSuccessful();

        self::assertSame(
            0,
            $this->numberOf("SELECT count(*) FROM identity.app_user WHERE email = 'nevine@example.org'"),
        );

        self::assertSame(0, $this->numberOf(
            'SELECT count(*) FROM people.membership WHERE person_id = ?',
            $nevine->id()->toRfc4122(),
        ));

        self::assertSame(0, $this->numberOf('SELECT count(*) FROM learning.homework_completion'));
    }

    public function testErasingOnlyTheAccountKeepsTheRecordAndTheReportWithoutItsAuthor(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $course = CopticCourse::seeded(self::getContainer());
        $nevine = $course->member('nevine@example.org', 'Nevine');
        $course->report('greeting', $nevine, 'nevine@example.org');

        $account = Type::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne("SELECT id::text FROM identity.app_user WHERE email = 'nevine@example.org'"),
        )->toString();

        $kernel = $this->browser()->getKernel();
        self::assertInstanceOf(KernelInterface::class, $kernel);

        new CommandTester(new Application($kernel)->find('app:privacy:erase-account'))->execute([
            'account' => $account,
        ]);

        self::assertSame(1, $this->numberOf(
            'SELECT count(*) FROM people.person WHERE id = ?',
            $nevine->id()->toRfc4122(),
        ));

        self::assertSame(
            1,
            $this->numberOf('SELECT count(*) FROM learning.homework_completion WHERE completed_by IS NULL'),
        );

        self::assertSame(0, $this->numberOf('SELECT count(*) FROM identity.app_user WHERE id = ?', $account));
    }

    private function numberOf(string $sql, string ...$parameters): int
    {
        return Integers::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne($sql, array_values($parameters)),
        );
    }
}
