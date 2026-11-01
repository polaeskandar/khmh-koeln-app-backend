<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Domain\ValueObject\Access;
use App\Library\Strings\Model\Text;
use Override;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Access>
 */
final class AccessVoter extends Voter
{
    public function __construct(
        private readonly CurrentAccounts $accounts,
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
        $user = $this->accounts->of($token);
        $granted = $user !== null && $subject->isAllowedFor($user);

        if (!$granted) {
            $vote?->addReason(Text::format('No active role of the account grants this %s.', $attribute)->toString());
        }

        return $granted;
    }
}
