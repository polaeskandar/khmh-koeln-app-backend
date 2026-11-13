<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Access\Domain\ValueObject;

use App\Context\Access\Domain\Entity\Permission;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Access\Domain\ValueObject\PermissionSelection;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PermissionSelectionTest extends TestCase
{
    public function testASelectionGrantsWhatWasChosenAndPicksItFromTheCatalog(): void
    {
        $readServices = new Permission(Action::Read, Resource::Services);
        $updateRoles = new Permission(Action::Update, Resource::Roles);
        $catalog = new ArrayCollection([$updateRoles, $readServices, new Permission(Action::Read, Resource::Lessons)]);

        $selection = PermissionSelection::of([
            ['action' => 'read', 'resource' => 'services'],
            ['action' => 'update', 'resource' => 'roles'],
        ]);

        self::assertTrue($selection->grants(Action::Update, Resource::Roles));
        self::assertFalse($selection->grants(Action::Read, Resource::Lessons));
        self::assertSame([$updateRoles, $readServices], $selection->permissionsIn($catalog));
        self::assertSame([], PermissionSelection::of([])->permissionsIn($catalog));
    }

    /**
     * @param list<array{action: string|null, resource: string|null}> $choices
     */
    #[DataProvider('refused')]
    public function testEveryChoiceIsFromTheCatalogAndChosenOnce(array $choices): void
    {
        self::assertSame(
            ['permissions' => ['code' => 'validation.invalidChoice']],
            ThrownFieldErrors::of(static fn (): PermissionSelection => PermissionSelection::of($choices)),
        );
    }

    /**
     * @return iterable<string, array{list<array{action: string|null, resource: string|null}>}>
     */
    public static function refused(): iterable
    {
        yield 'unknown action' => [[['action' => 'launch', 'resource' => 'services']]];
        yield 'unknown resource' => [[['action' => 'read', 'resource' => 'rockets']]];
        yield 'no action' => [[['action' => null, 'resource' => 'services']]];
        yield 'no resource' => [[['action' => 'read', 'resource' => null]]];

        yield 'twice' => [[
            ['action' => 'read', 'resource' => 'services'],
            ['action' => 'read', 'resource' => 'services'],
        ]];
    }

    public function testAPermissionMissingFromTheCatalogIsRefused(): void
    {
        $selection = PermissionSelection::of([['action' => 'read', 'resource' => 'services']]);

        self::assertSame(
            ['permissions' => ['code' => 'validation.invalidChoice']],
            ThrownFieldErrors::of(static fn (): array => $selection->permissionsIn(new ArrayCollection())),
        );
    }
}
