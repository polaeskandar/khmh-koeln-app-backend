<?php

declare(strict_types=1);

namespace App\Platform\Lifecycle\Service;

use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Types\Model\Type;
use App\Platform\Lifecycle\Contract\Transitions;
use Doctrine\Common\Collections\ArrayCollection;
use Override;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\TransitionBlocker;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class StateMachineTransitions implements Transitions
{
    private const string REFUSALS = 'refusals';

    #[Override]
    public function apply(WorkflowInterface $lifecycle, object $subject, string $transition): bool
    {
        $marking = $lifecycle->getMarking($subject);
        $from = $marking->getPlaces();

        try {
            return $lifecycle->apply($subject, $transition)->getPlaces() !== $from;
        } catch (NotEnabledTransitionException $refused) {
            throw self::refusal($lifecycle, $refused, $marking);
        }
    }

    private static function refusal(
        WorkflowInterface $lifecycle,
        NotEnabledTransitionException $refused,
        Marking $marking,
    ): RuleViolation {
        $blockers = $refused->getTransitionBlockerList();

        if ($blockers->has(TransitionBlocker::BLOCKED_BY_MARKING)) {
            return new RuleViolation(self::refusalFrom($lifecycle, $refused->getTransitionName(), $marking));
        }

        return new RuleViolation(
            Type::of(Collections::of($blockers)->first())->toInstanceOf(TransitionBlocker::class)->getCode(),
        );
    }

    private static function refusalFrom(WorkflowInterface $lifecycle, string $transition, Marking $marking): string
    {
        $named = new ArrayCollection($lifecycle->getDefinition()->getTransitions())
            ->filter(static fn (Transition $candidate): bool => $candidate->getName() === $transition)
            ->first();

        $refusals = Type::of($lifecycle->getMetadataStore()->getMetadata(
            self::REFUSALS,
            Type::of($named)->toInstanceOf(Transition::class),
        ));

        return $refusals->toStringAt(new ArrayCollection($marking->getPlaces())->getKeys()[0] ?? '');
    }
}
