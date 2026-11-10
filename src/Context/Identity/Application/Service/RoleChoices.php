<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Model\Ids;
use App\Library\Strings\Model\Text;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class RoleChoices
{
    public function __construct(
        private RoleRepositoryInterface $roles,
    ) {
    }

    /**
     * @param list<string> $roleIds
     *
     * @return ReadableCollection<int, Role>
     */
    public function chosen(array $roleIds): ReadableCollection
    {
        $ids = Collections::firstPerKey(
            new ArrayCollection($roleIds),
            keyOf: static fn (string $id): string => Text::of($id)->lower()->toString(),
        );

        if ($ids->isEmpty()) {
            throw InvalidInput::field('roleIds', 'roles.chooseRole');
        }

        $chosen = $this->roles->findByIds(Collections::mapPresent($ids, Ids::toUuidOrNull(...))->getValues());

        return $chosen->count() === $ids->count() ? $chosen : throw InvalidInput::field('roleIds', 'roles.unknown');
    }

    /**
     * @param list<string> $roleIds
     *
     * @return ReadableCollection<int, Role>
     */
    public function chosenWithParent(array $roleIds): ReadableCollection
    {
        $parent = $this->roles->findByName(ParentAccounts::PARENT_ROLE) ?? throw NotFound::of('role');
        $chosen = $this->chosen($roleIds);

        $roles = Collections::firstPerKey(
            new ArrayCollection([...$chosen->getValues(), $parent]),
            keyOf: static fn (Role $role): string => $role->id()->toRfc4122(),
        );

        return new ArrayCollection($roles->getValues());
    }
}
