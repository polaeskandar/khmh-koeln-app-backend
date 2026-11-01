<?php

declare(strict_types=1);

namespace App\Interface\Application\Identity\UseCase\Query\ShowCurrentAccount;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Interface\Application\Identity\Model\AccountSummary;
use App\Interface\Application\Identity\Model\CurrentAccount;
use App\Interface\Application\Identity\Model\PermissionGrant;
use App\Interface\Application\Identity\Model\RoleSummary;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Symfony\Component\Uid\Uuid;

final readonly class ShowCurrentAccountHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(ShowCurrentAccountQuery $query): CurrentAccount
    {
        $user = $this->users->find(Uuid::fromString($query->userId)) ?? throw NotFound::of('account');

        return new CurrentAccount(
            account: self::summaryOf($user),
            roles: self::rolesOf($user),
            permissions: Collections::of(self::grants($user))->getValues(),
            today: $this->calendar->today()->toString(),
        );
    }

    private static function summaryOf(User $user): AccountSummary
    {
        $profile = $user->profile();

        return new AccountSummary(
            $user->id()->toRfc4122(),
            $profile->email->value,
            $profile->displayName->value,
            $profile->language->value,
        );
    }

    /**
     * @return iterable<int, PermissionGrant>
     */
    private static function grants(User $user): iterable
    {
        foreach (Resource::cases() as $resource) {
            foreach (Action::cases() as $action) {
                if (!$user->can($action, $resource)) {
                    continue;
                }

                yield new PermissionGrant($action->value, $resource->value);
            }
        }
    }

    /**
     * @return list<RoleSummary>
     */
    private static function rolesOf(User $user): array
    {
        return Collections::of($user->roles()->map(
            static fn (Role $role): RoleSummary => new RoleSummary($role->id()->toRfc4122(), $role->name()->value),
        ))->matching(Criteria::create()->orderBy(['name' => Order::Ascending]))->getValues();
    }
}
