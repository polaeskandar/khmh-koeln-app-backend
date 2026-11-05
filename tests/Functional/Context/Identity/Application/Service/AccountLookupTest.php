<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Application\Service;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class AccountLookupTest extends KernelTestCase
{
    public function testActiveAccountsThatPrepareLessonsByName(): void
    {
        $daniel = $this->account('Daniel Michael', [], 'servant');
        $christine = $this->account('Christine Adel', [], 'service_leader');
        $both = $this->account('Ölga Bishara', [], 'servant', 'service_leader');
        $parent = $this->account('Mariam Hanna', [], 'parent');
        $disabled = $this->account('Mina Iskander', ['disabled_at' => '2026-11-09 12:00:00+01'], 'servant');
        $pending = $this->account('Sara Hanna', ['approved_at' => null], 'servant');

        self::assertSame(
            ['Christine Adel', 'Daniel Michael', 'Ölga Bishara'],
            array_map(
                static fn (User $account): string => $account->profile()->displayName->value,
                $this->lookup()->activeAllowedTo(Action::Update, Resource::Lessons),
            ),
        );

        foreach ([$daniel, $christine, $both] as $id) {
            self::assertTrue($this->lookup()->isActiveAndAllowedTo(
                Uuid::fromString($id),
                Action::Update,
                Resource::Lessons,
            ));
        }

        foreach ([$parent, $disabled, $pending] as $id) {
            self::assertFalse($this->lookup()->isActiveAndAllowedTo(
                Uuid::fromString($id),
                Action::Update,
                Resource::Lessons,
            ));
        }
    }

    /**
     * @param array<string, string|null> $columns
     */
    private function account(string $name, array $columns, string ...$roles): string
    {
        $id = Uuid::v7()->toRfc4122();

        new Database(self::getContainer())
            ->connection()
            ->insert('identity.app_user', Accounts::row([
                'id' => $id,
                'email' => $id . '@example.org',
                'display_name' => $name,
                ...$columns,
            ]));

        new RoleGrants(self::getContainer())->grant($id, ...$roles);

        return $id;
    }

    private function lookup(): AccountLookup
    {
        $lookup = self::getContainer()->get(AccountLookup::class);
        self::assertInstanceOf(AccountLookup::class, $lookup);

        return $lookup;
    }
}
