<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Library\Problem\Exception\RuleViolation;
use Doctrine\Common\Collections\ReadableCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class AccountRequests
{
    private const int LISTED = 200;

    public function __construct(
        private UserRepositoryInterface $users,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return ReadableCollection<int, User>
     */
    public function pending(): ReadableCollection
    {
        return $this->users->pending(self::LISTED);
    }

    /**
     * @param callable(User): void $decision
     */
    public function decide(string $id, callable $decision): void
    {
        $this->users->withWriteLock(Uuid::fromString($id), static function (User $user) use ($decision): void {
            if (!$user->status()->isPending()) {
                throw new RuleViolation('accounts.alreadyApproved');
            }

            $decision($user);
        });
    }

    public function decline(User $user): void
    {
        $this->users->remove($user);
        $this->logger->info('Declined an account request.', ['account' => $user->id()->toRfc4122()]);
    }
}
