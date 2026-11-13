<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Application\Service;

use App\Context\Access\Application\Service\RoleHolders;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Tests\Support\Context\Access\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class AccountLookupTest extends KernelTestCase
{
    public function testActiveAccountsThatPrepareLessonsByName(): void
    {
        $this->account('Daniel Michael', [], 'servant');
        $this->account('Christine Adel', [], 'service_leader');
        $this->account('Ölga Bishara', [], 'servant', 'service_leader');
        $this->account('Bishoy Fahmy', [], 'administrator');
        $this->account('Mariam Hanna', [], 'parent');
        $this->account('Mina Iskander', ['disabled_at' => '2026-11-09 12:00:00+01'], 'servant');

        $holders = self::getContainer()->get(RoleHolders::class);
        self::assertInstanceOf(RoleHolders::class, $holders);
        $named = $this->lookup()->activeNamedByIds($holders->thatCan(Action::Update, Resource::Lessons));

        self::assertSame(
            ['Bishoy Fahmy', 'Christine Adel', 'Daniel Michael', 'Ölga Bishara'],
            array_map($named->nameOf(...), $named->byName()),
        );
    }

    /**
     * @param array<string, string|null> $columns
     */
    private function account(string $name, array $columns, string ...$roles): string
    {
        $id = Uuid::v7()->toRfc4122();

        $connection = new Database(self::getContainer())->connection();

        $connection->insert(
            'identity.app_user',
            Accounts::row(Accounts::named($connection, [
                'id' => $id,
                'email' => $id . '@example.org',
                'display_name' => $name,
                ...$columns,
            ])),
        );

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
