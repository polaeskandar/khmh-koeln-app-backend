<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Domain\ValueObject\AccountAreas;
use App\Library\Strings\Model\Text;
use Doctrine\Common\Collections\ArrayCollection;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final readonly class AccountAreaVoter implements VoterInterface
{
    private const array AREAS = [AccountAreas::STAFF, AccountAreas::PARENT_PORTAL];

    public function __construct(
        private CurrentAccounts $accounts,
    ) {
    }

    /**
     * @param array<array-key, mixed> $attributes
     */
    #[Override]
    public function vote(
        #[\SensitiveParameter]
        TokenInterface $token,
        mixed $subject,
        array $attributes,
        ?Vote $vote = null,
    ): int {
        $given = new ArrayCollection($attributes);
        $asked = new ArrayCollection(self::AREAS)->filter($given->contains(...));

        if ($asked->isEmpty()) {
            return self::ACCESS_ABSTAIN;
        }

        $user = $this->accounts->of($token);
        $opened = $user === null ? new ArrayCollection() : $asked->filter(AccountAreas::of($user)->opens(...));
        $target = $subject instanceof Request ? $subject->getPathInfo() : 'the request';

        $vote?->addReason(
            Text::format('The account opens %d of the areas %s needs.', $opened->count(), $target)->toString(),
        );

        return $opened->isEmpty() ? self::ACCESS_DENIED : self::ACCESS_GRANTED;
    }
}
