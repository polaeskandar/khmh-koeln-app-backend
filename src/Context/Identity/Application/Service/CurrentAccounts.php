<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\Access;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\NotFound;
use Symfony\Component\Uid\Uuid;

final readonly class CurrentAccounts
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function find(string $userId): User
    {
        return $this->users->find(Uuid::fromString($userId)) ?? throw NotFound::of('account');
    }

    /**
     * @return list<Access>
     */
    public function grantsOf(User $user): array
    {
        return Collections::of(self::grants($user))->getValues();
    }

    /**
     * @return iterable<int, Access>
     */
    private static function grants(User $user): iterable
    {
        foreach (Resource::cases() as $resource) {
            foreach (Action::cases() as $action) {
                if (!$user->can($action, $resource)) {
                    continue;
                }

                yield new Access($action, $resource);
            }
        }
    }
}
