<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Application\Service;

use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Tests\Support\Context\Identity\Application\Service\RoleAssignmentFixtures;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RoleAssignmentsTest extends KernelTestCase
{
    public function testGivingARoleStoresIt(): void
    {
        $this->fixtures()->account('sara@example.org');

        $this->fixtures()->assignments()->give('sara@example.org', 'Servant');

        self::assertSame(['servant'], $this->fixtures()->roleKeysOf('sara@example.org'));
    }

    public function testARoleIsGivenOnlyOnce(): void
    {
        $this->fixtures()->account('sara@example.org', [], 'servant');

        $this->assertRefused('roles.alreadyHasRole', fn () => $this
            ->fixtures()
            ->assignments()
            ->give('sara@example.org', 'Servant'));

        self::assertSame(['servant'], $this->fixtures()->roleKeysOf('sara@example.org'));
    }

    public function testOnlyAHeldRoleCanBeTaken(): void
    {
        $this->fixtures()->account('sara@example.org');

        $this->assertRefused('roles.notHeld', fn () => $this
            ->fixtures()
            ->assignments()
            ->take('sara@example.org', 'Servant'));
    }

    public function testTheLastActiveAdministratorKeepsTheRole(): void
    {
        $admin = $this->fixtures()->account('admin@example.org', [], 'administrator');

        $this->fixtures()->account(
            'disabled@example.org',
            ['disabled_at' => '2026-11-09 12:00:00+01'],
            'administrator',
        );

        self::assertTrue($this->fixtures()->lastAdministrator()->isKeptOnlyByAccount($admin));

        $this->assertRefused('roles.lastAdministrator', fn () => $this
            ->fixtures()
            ->assignments()
            ->take('admin@example.org', 'Administrator'));

        self::assertSame(['administrator'], $this->fixtures()->roleKeysOf('admin@example.org'));
    }

    public function testWithAnotherActiveAdministratorTheRoleCanBeTaken(): void
    {
        $admin = $this->fixtures()->account('admin@example.org', [], 'administrator');
        $this->fixtures()->account('other@example.org', [], 'administrator');

        self::assertFalse($this->fixtures()->lastAdministrator()->isKeptOnlyByAccount($admin));
        $this->fixtures()->assignments()->take('admin@example.org', 'Administrator');

        self::assertSame([], $this->fixtures()->roleKeysOf('admin@example.org'));
    }

    public function testOtherRolesOfTheLastAdministratorCanBeTaken(): void
    {
        $this->fixtures()->account('admin@example.org', [], 'administrator', 'servant');

        $this->fixtures()->assignments()->take('admin@example.org', 'Servant');

        self::assertSame(['administrator'], $this->fixtures()->roleKeysOf('admin@example.org'));
    }

    public function testAnUnknownAccountOrRoleIsNotFound(): void
    {
        $this->fixtures()->account('sara@example.org');

        $this->assertRefused('notFound.account', fn () => $this
            ->fixtures()
            ->assignments()
            ->give('nobody@example.org', 'Servant'));

        $this->assertRefused('notFound.role', fn () => $this
            ->fixtures()
            ->assignments()
            ->take('sara@example.org', 'Choir Director'));
    }

    private function assertRefused(string $code, callable $change): void
    {
        try {
            $change();
        } catch (NotFound|RuleViolation $refused) {
            self::assertSame($code, $refused->getMessage());
            $this->fixtures()->forget();

            return;
        }

        self::fail('Expected ' . $code);
    }

    private function fixtures(): RoleAssignmentFixtures
    {
        return new RoleAssignmentFixtures(self::getContainer());
    }
}
