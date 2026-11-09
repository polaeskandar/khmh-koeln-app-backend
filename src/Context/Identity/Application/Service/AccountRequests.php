<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
use App\Context\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use Doctrine\Common\Collections\ReadableCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class AccountRequests
{
    private const int LISTED = 200;

    public function __construct(
        private AccountRepositoryInterface $accounts,
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
     * @return ReadableCollection<int, Account>
     */
    public function pending(): ReadableCollection
    {
        return $this->accounts->pending(self::LISTED);
    }

    public function ensurePending(string $id): void
    {
        self::ensureUndecided($this->accounts->find(self::idOf($id)) ?? throw NotFound::of('user'));
    }

    /**
     * @param callable(Account): void $decision
     */
    public function whileUndecided(string $id, callable $decision): void
    {
        $this->accounts->withWriteLock(self::idOf($id), static function (Account $account) use ($decision): void {
            self::ensureUndecided($account);
            $decision($account);
        });
    }

    public function decline(Account $account): void
    {
        $this->accounts->remove($account);
        $this->logger->info('Declined an account request.', ['account' => $account->id()->toRfc4122()]);
    }

    private static function idOf(string $id): Uuid
    {
        return Uuid::isValid($id) ? Uuid::fromString($id) : throw NotFound::of('user');
    }

    private static function ensureUndecided(Account $account): void
    {
        if (!$account->status()->isPending()) {
            throw new RuleViolation('accounts.alreadyApproved');
        }
    }
}
