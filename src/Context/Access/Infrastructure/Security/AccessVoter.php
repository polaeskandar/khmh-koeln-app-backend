<?php

declare(strict_types=1);

namespace App\Context\Access\Infrastructure\Security;

use App\Context\Access\Application\Service\RolePermissions;
use App\Context\Access\Domain\Repository\RoleHolderRepositoryInterface;
use App\Context\Access\Domain\ValueObject\Access;
use App\Library\Strings\Model\Text;
use App\Platform\Access\Contract\ActiveAccounts;
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
        private readonly RoleHolderRepositoryInterface $holders,
        private readonly ActiveAccounts $accounts,
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
        $accountId = self::accountOf($token);

        $granted =
            $accountId !== null
            && $this->accounts->isEnabled($accountId)
            && RolePermissions::isGrantedToAny(
                $this->holders->listRolesOf($accountId),
                $subject->action,
                $subject->resource,
            );

        if (!$granted) {
            $vote?->addReason(Text::format('No active role of the account grants this %s.', $attribute)->toString());
        }

        return $granted;
    }

    private static function accountOf(#[\SensitiveParameter] TokenInterface $token): ?Uuid
    {
        $identifier = $token->getUser()?->getUserIdentifier();

        return $identifier === null || !Uuid::isValid($identifier) ? null : Uuid::fromString($identifier);
    }
}
