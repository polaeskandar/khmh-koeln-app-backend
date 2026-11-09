<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Enum\RegisteredAs;
use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\MemberLink;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use Symfony\Component\Uid\Uuid;

final readonly class MemberLinkRemovals
{
    public function __construct(
        private AccountRepositoryInterface $accounts,
    ) {
    }

    public function removeFrom(string $accountId): void
    {
        $id = Uuid::isValid($accountId) ? Uuid::fromString($accountId) : throw NotFound::of('user');
        $this->accounts->withWriteLock($id, self::unlinked(...));
    }

    public function removeByEmail(string $email): void
    {
        $account = $this->accounts->findByEmail($email) ?? throw NotFound::of('user');

        if ($account->member()->personId !== null) {
            $this->accounts->withWriteLock($account->id(), self::unlinked(...));
        }
    }

    private static function unlinked(Account $account): void
    {
        if ($account->member()->personId === null) {
            throw new RuleViolation('accounts.notLinked');
        }

        $account->changeMember(MemberLink::none());

        if ($account->roles()->isEmpty()) {
            $account->changeApproval(
                by: null,
                status: $account->status()->reopened(),
                registration: $account->registration()->reopenedAs(RegisteredAs::Member),
            );
        }
    }
}
