<?php

declare(strict_types=1);

namespace App\Context\Access\Application\Service;

use App\Context\Access\Domain\Entity\Role;
use App\Context\Access\Domain\Repository\RoleRepositoryInterface;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\InvalidInput;
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
}
