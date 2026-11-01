<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Domain\Service;

use App\Library\Problem\Exception\RuleViolation;
use App\Tests\Support\Context\Identity\Domain\Service\RoleAssignmentFixtures;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RoleAssignmentsTest extends KernelTestCase
{
    public function testGivingARoleStoresIt(): void
    {
        $user = $this->fixtures()->account('sara@example.org');

        $this->fixtures()->assignments()->give($user, $this->fixtures()->role('Servant'));

        self::assertSame(['servant'], $this->fixtures()->roleKeysOf('sara@example.org'));
    }

    public function testARoleIsGivenOnlyOnce(): void
    {
        $user = $this->fixtures()->account('sara@example.org', [], 'servant');

        $this->assertRefused('roles.alreadyHasRole', fn () => $this
            ->fixtures()
            ->assignments()
            ->give($user, $this->fixtures()->role('Servant')));

        self::assertSame(['servant'], $this->fixtures()->roleKeysOf('sara@example.org'));
    }

    public function testAPendingAccountGetsNoRole(): void
    {
        $user = $this->fixtures()->account('sara@example.org', ['approved_at' => null]);

        $this->assertRefused('roles.accountPending', fn () => $this
            ->fixtures()
            ->assignments()
            ->give($user, $this->fixtures()->role('Servant')));

        self::assertSame([], $this->fixtures()->roleKeysOf('sara@example.org'));
    }

    public function testOnlyAHeldRoleCanBeTaken(): void
    {
        $user = $this->fixtures()->account('sara@example.org');

        $this->assertRefused('roles.notHeld', fn () => $this
            ->fixtures()
            ->assignments()
            ->take($user, $this->fixtures()->role('Servant')));
    }

    public function testTheLastActiveAdministratorKeepsTheRole(): void
    {
        $admin = $this->fixtures()->account('admin@example.org', [], 'administrator');
        $this->fixtures()->account('pending@example.org', ['approved_at' => null], 'administrator');

        $this->fixtures()->account(
            'disabled@example.org',
            ['disabled_at' => '2026-11-09 12:00:00+01'],
            'administrator',
        );

        self::assertTrue($this->fixtures()->lastAdministrator()->is($admin));

        $this->assertRefused('roles.lastAdministrator', fn () => $this
            ->fixtures()
            ->assignments()
            ->take($admin, $this->fixtures()->role('Administrator')));

        self::assertSame(['administrator'], $this->fixtures()->roleKeysOf('admin@example.org'));
    }

    public function testWithAnotherActiveAdministratorTheRoleCanBeTaken(): void
    {
        $admin = $this->fixtures()->account('admin@example.org', [], 'administrator');
        $this->fixtures()->account('other@example.org', [], 'administrator');

        self::assertFalse($this->fixtures()->lastAdministrator()->is($admin));
        $this->fixtures()->assignments()->take($admin, $this->fixtures()->role('Administrator'));

        self::assertSame([], $this->fixtures()->roleKeysOf('admin@example.org'));
    }

    public function testOtherRolesOfTheLastAdministratorCanBeTaken(): void
    {
        $admin = $this->fixtures()->account('admin@example.org', [], 'administrator', 'servant');

        $this->fixtures()->assignments()->take($admin, $this->fixtures()->role('Servant'));

        self::assertSame(['administrator'], $this->fixtures()->roleKeysOf('admin@example.org'));
    }

    private function assertRefused(string $code, callable $change): void
    {
        try {
            $change();
        } catch (RuleViolation $violation) {
            self::assertSame($code, $violation->getMessage());
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
