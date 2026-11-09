<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Application\Service\RolePermissions;
use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\Access;
use App\Library\Strings\Model\Text;
use Override;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Uid\Uuid;

/**
 * @extends Voter<string, Access>
 */
final class AccessVoter extends Voter
{
    public function __construct(
        private readonly AccountRepositoryInterface $accounts,
    ) {
    }

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === Access::ATTRIBUTE && $subject instanceof Access;
    }

    #[Override]
    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        #[\SensitiveParameter]
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        $account = $this->accountOf($token);

        $granted =
            $account !== null && RolePermissions::activeAccountHas($account, $subject->action, $subject->resource);

        if (!$granted) {
            $vote?->addReason(Text::format('No active role of the account grants this %s.', $attribute)->toString());
        }

        return $granted;
    }

    private function accountOf(#[\SensitiveParameter] TokenInterface $token): ?Account
    {
        $user = $token->getUser();

        return $user instanceof SecurityUser
            ? $this->accounts->find(Uuid::fromString($user->getUserIdentifier()))
            : null;
    }
}
