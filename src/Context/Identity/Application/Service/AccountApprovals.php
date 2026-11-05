<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use App\Library\Strings\Model\Text;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class AccountApprovals
{
    public function __construct(
        private AccountRequests $requests,
        private UserRepositoryInterface $users,
        private RoleRepositoryInterface $roles,
        private Calendar $calendar,
        private ApprovalNotices $notices,
    ) {
    }

    /**
     * @param list<string> $roleIds
     *
     * @return ReadableCollection<int, Role>
     */
    public function chosenRoles(array $roleIds): ReadableCollection
    {
        $ids = Collections::firstPerKey(
            new ArrayCollection($roleIds),
            keyOf: static fn (string $id): string => Text::of($id)->lower()->toString(),
        );

        if ($ids->isEmpty()) {
            throw InvalidInput::field('roleIds', 'roles.chooseRole');
        }

        $chosen = $this->roles->findByIds(Collections::mapPresent($ids, self::uuidOf(...))->getValues());

        return $chosen->count() === $ids->count() ? $chosen : throw InvalidInput::field('roleIds', 'roles.unknown');
    }

    /**
     * @param list<string> $roleIds
     *
     * @return ReadableCollection<int, Role>
     */
    public function chosenParentRoles(array $roleIds): ReadableCollection
    {
        $parent = $this->roles->findByName(ParentAccounts::PARENT_ROLE) ?? throw NotFound::of('role');
        $chosen = $this->chosenRoles($roleIds);

        $roles = Collections::firstPerKey(
            new ArrayCollection([...$chosen->getValues(), $parent]),
            keyOf: static fn (Role $role): string => $role->id()->toRfc4122(),
        );

        return new ArrayCollection($roles->getValues());
    }

    public function ensurePending(string $id): void
    {
        $this->requests->ensurePending($id);
    }

    /**
     * @param ReadableCollection<int, Role> $roles
     * @param callable(User): void $alongside
     */
    public function approve(string $id, string $actorId, ReadableCollection $roles, callable $alongside): User
    {
        $actor = $this->account($actorId);

        $this->requests->decide($id, function (User $user) use ($actor, $roles, $alongside): void {
            $user->changeApproval(
                $actor,
                $user->status()->approvedOn($this->calendar->now()),
                $user->registration()->decided(),
            );

            foreach ($roles as $role) {
                $user->assign($role);
            }

            $alongside($user);
        });

        return $this->account($id);
    }

    public function announce(User $approved): void
    {
        $this->notices->send($approved);
    }

    public function account(string $id): User
    {
        return $this->users->find(Uuid::fromString($id)) ?? throw NotFound::of('user');
    }

    private static function uuidOf(string $id): ?Uuid
    {
        return Uuid::isValid($id) ? Uuid::fromString($id) : null;
    }
}
