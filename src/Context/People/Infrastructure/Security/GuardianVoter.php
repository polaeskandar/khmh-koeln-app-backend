<?php

declare(strict_types=1);

namespace App\Context\People\Infrastructure\Security;

use App\Context\People\Application\Service\Guardianship;
use App\Context\People\Domain\Entity\Person;
use App\Library\Strings\Model\Text;
use Doctrine\Common\Collections\ArrayCollection;
use Override;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final readonly class GuardianVoter implements VoterInterface
{
    public const string ACT_FOR_CHILD = 'ACT_FOR_CHILD';

    public function __construct(
        private Guardianship $guardianship,
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
        if (!$subject instanceof Person || !new ArrayCollection($attributes)->contains(self::ACT_FOR_CHILD)) {
            return self::ACCESS_ABSTAIN;
        }

        $granted = $this->guardianship->isGuardianOf($token->getUserIdentifier(), $subject);
        $vote?->addReason(Text::format('Approved guardian link: %s.', $granted ? 'yes' : 'no')->toString());

        return $granted ? self::ACCESS_GRANTED : self::ACCESS_DENIED;
    }
}
