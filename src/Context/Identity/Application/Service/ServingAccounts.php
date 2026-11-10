<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\RoleScope;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\NotFound;
use Collator;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ServingAccounts
{
    private const string COLLATION = 'de';

    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function found(string $id): User
    {
        $account = Uuid::isValid($id) ? $this->users->find(Uuid::fromString($id)) : null;

        return $account ?? throw NotFound::of('user');
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return list<User> the active ones with a role that holds in assigned groups, by name
     */
    public function activeAmong(array $ids): array
    {
        return self::byName(
            $this->users
                ->findByIds($ids)
                ->filter(
                    static fn (User $user): bool => $user->status()->isActive()
                    && !$user
                        ->roles()
                        ->filter(static fn (Role $role): bool => $role->scope() === RoleScope::AssignedGroups)
                        ->isEmpty(),
                ),
        );
    }

    /**
     * @param ReadableCollection<int, User> $users
     *
     * @return list<User>
     */
    private static function byName(ReadableCollection $users): array
    {
        $collator = new Collator(self::COLLATION);

        $nameKeyOf =
            static fn (User $user): string => (string) $collator->getSortKey($user->profile()->displayName->value);

        return Collections::sortedBy($users, keyOf: $nameKeyOf);
    }
}
