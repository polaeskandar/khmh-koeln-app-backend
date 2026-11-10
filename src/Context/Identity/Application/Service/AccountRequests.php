<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use Doctrine\Common\Collections\ReadableCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class AccountRequests
{
    private const int LISTED = 200;

    public function __construct(
        private UserRepositoryInterface $users,
        private RoleRepositoryInterface $roles,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return ReadableCollection<int, Role>
     */
    public function roleChoices(): ReadableCollection
    {
        return $this->roles->byName();
    }

    /**
     * @return ReadableCollection<int, User>
     */
    public function pending(): ReadableCollection
    {
        return $this->users->pending(self::LISTED);
    }

    public function ensurePending(Uuid $id): void
    {
        self::ensureUndecided($this->users->find($id) ?? throw NotFound::of('user'));
    }

    /**
     * @param callable(User): void $decision
     */
    public function decide(Uuid $id, callable $decision): void
    {
        $this->users->withWriteLock($id, static function (User $user) use ($decision): void {
            self::ensureUndecided($user);
            $decision($user);
        });
    }

    public function decline(User $user): void
    {
        $this->users->remove($user);
        $this->logger->info('Declined an account request.', ['account' => $user->id()->toRfc4122()]);
    }

    private static function ensureUndecided(User $user): void
    {
        if (!$user->status()->isPending()) {
            throw new RuleViolation('accounts.alreadyApproved');
        }
    }
}
