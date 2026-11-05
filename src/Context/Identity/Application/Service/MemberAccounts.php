<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\AgeCheck;
use App\Context\Identity\Domain\Repository\MemberAccountRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\MemberLink;
use App\Context\Identity\Domain\ValueObject\MemberRecord;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

final readonly class MemberAccounts
{
    public function __construct(
        private UserRepositoryInterface $users,
        private MemberAccountRepositoryInterface $members,
    ) {
    }

    public function linkable(string $email): User
    {
        $account = $this->byEmail($email);

        return $account->status()->isActive() ? $account : throw new RuleViolation('accounts.accountInactive');
    }

    public function link(User $account, MemberRecord $record, string $ageCheck, ?string $note): void
    {
        if ($account->member()->isLinkedTo($record->personId)) {
            return;
        }

        $this->ensureFree($record);
        $age = AgeCheck::tryFrom($ageCheck) ?? throw InvalidInput::field('ageCheck', 'accounts.chooseAge');
        $account->changeMember(MemberLink::of($record->personId, $age, $note));

        try {
            $this->users->save($account);
        } catch (UniqueConstraintViolationException) {
            throw $record->taken();
        }
    }

    /**
     * @param list<Uuid> $personIds
     *
     * @return list<string>
     */
    public function linkedAmong(array $personIds): array
    {
        return $this->members->linkedAmong($personIds);
    }

    /**
     * @return list<array{
     *     id: string,
     *     name: string,
     *     email: string,
     *     disabled: bool,
     *     isParent: bool,
     *     personId: string,
     *     ageCheck: string|null,
     *     guardianConsentNote: string|null,
     *     approvedAt: string|null,
     *     approverId: string|null,
     *     approverName: string|null,
     * }> the linked accounts without a staff role
     */
    public function listed(): array
    {
        return $this->members->listed();
    }

    private function ensureFree(MemberRecord $record): void
    {
        if ($this->members->holderOf($record->personId) !== null) {
            throw $record->taken();
        }
    }

    private function byEmail(string $email): User
    {
        return $this->users->findByEmail($email) ?? throw NotFound::of('user');
    }
}
