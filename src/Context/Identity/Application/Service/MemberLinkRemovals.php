<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\RegisteredAs;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\MemberLink;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use Symfony\Component\Uid\Uuid;

final readonly class MemberLinkRemovals
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function removeFrom(string $accountId): void
    {
        $this->users->withWriteLock(Uuid::fromString($accountId), self::unlinked(...));
    }

    public function removeByEmail(string $email): void
    {
        $account = $this->users->findByEmail($email) ?? throw NotFound::of('user');

        if ($account->member()->personId !== null) {
            $this->users->withWriteLock($account->id(), self::unlinked(...));
        }
    }

    private static function unlinked(User $account): void
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
