<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Language;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\Access;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use Symfony\Component\Uid\Uuid;

final readonly class CurrentAccounts
{
    public function __construct(
        private AccountRepositoryInterface $accounts,
    ) {
    }

    public function get(string $accountId): Account
    {
        return $this->accounts->find(Uuid::fromString($accountId)) ?? throw NotFound::of('account');
    }

    public function changeLanguage(string $accountId, string $language): void
    {
        $chosen = Language::tryFrom($language) ?? throw InvalidInput::field('language', 'validation.invalidType');

        $this->accounts->withWriteLock(
            Uuid::fromString($accountId),
            static fn (Account $account) => $account->changeLanguage($chosen),
        );
    }

    /**
     * @return list<Access>
     */
    public function grantsOf(Account $account): array
    {
        return Collections::of(self::grants($account))->getValues();
    }

    /**
     * @return iterable<int, Access>
     */
    private static function grants(Account $account): iterable
    {
        foreach (Resource::cases() as $resource) {
            foreach (Action::cases() as $action) {
                if (!RolePermissions::accountHas($account, $action, $resource)) {
                    continue;
                }

                yield new Access($action, $resource);
            }
        }
    }
}
