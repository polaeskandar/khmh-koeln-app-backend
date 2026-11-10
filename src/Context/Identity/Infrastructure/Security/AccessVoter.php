<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Application\Service\RolePermissions;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
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
        private readonly UserRepositoryInterface $users,
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
        $user = $this->accountOf($token);

        $granted =
            $user !== null && RolePermissions::isGrantedToActiveAccount($user, $subject->action, $subject->resource);

        if (!$granted) {
            $vote?->addReason(Text::format('No active role of the account grants this %s.', $attribute)->toString());
        }

        return $granted;
    }

    private function accountOf(#[\SensitiveParameter] TokenInterface $token): ?User
    {
        $account = $token->getUser();

        return $account instanceof SecurityUser
            ? $this->users->find(Uuid::fromString($account->getUserIdentifier()))
            : null;
    }
}
