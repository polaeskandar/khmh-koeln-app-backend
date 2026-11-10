<?php

declare(strict_types=1);

namespace App\Tests\Unit\Platform\Lifecycle\Service;

use App\Library\Problem\Exception\RuleViolation;
use App\Platform\Lifecycle\Service\StateMachineTransitions;
use PHPUnit\Framework\TestCase;
use SplObjectStorage;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\TransitionBlocker;

final class StateMachineTransitionsTest extends TestCase
{
    private const array OPEN_REFUSALS = ['locked' => 'door.locked', 'broken' => 'door.broken'];

    private const array CLOSE_REFUSALS = [
        'closed' => 'door.alreadyClosed',
        'locked' => 'door.locked',
        'broken' => 'door.broken',
    ];

    public function testATransitionMovesTheSubjectToItsPlace(): void
    {
        $door = self::door();
        $subject = self::subject('closed');

        self::assertTrue(new StateMachineTransitions()->apply($door, $subject, 'open'));
        self::assertTrue($door->getMarking($subject)->has('opened'));
    }

    public function testATransitionBackToThePlaceItStartsFromLeavesTheSubjectThere(): void
    {
        $door = self::door();
        $subject = self::subject('opened');

        self::assertFalse(new StateMachineTransitions()->apply($door, $subject, 'open'));
        self::assertTrue($door->getMarking($subject)->has('opened'));
    }

    public function testATransitionFromAnotherPlaceIsRefusedWithTheCodeOfThatPlace(): void
    {
        self::assertSame('door.locked', self::refusal(self::door(), 'locked', 'open'));
        self::assertSame('door.broken', self::refusal(self::door(), 'broken', 'open'));
        self::assertSame('door.alreadyClosed', self::refusal(self::door(), 'closed', 'close'));
    }

    public function testAGuardRefusesWithItsCodeOnlyWhereThePlaceAllowsTheTransition(): void
    {
        self::assertSame('door.jammed', self::refusal(self::door(guard: 'door.jammed'), 'closed', 'open'));
        self::assertSame('door.jammed', self::refusal(self::door(guard: 'door.jammed'), 'opened', 'open'));
        self::assertSame('door.locked', self::refusal(self::door(guard: 'door.jammed'), 'locked', 'open'));
    }

    private static function refusal(StateMachine $door, string $place, string $transition): ?string
    {
        $subject = self::subject($place);

        try {
            new StateMachineTransitions()->apply($door, $subject, $transition);
        } catch (RuleViolation $violation) {
            self::assertTrue($door->getMarking($subject)->has($place));

            return $violation->getMessage();
        }

        return null;
    }

    private static function door(?string $guard = null): StateMachine
    {
        $open = new Transition('open', 'closed', 'opened');
        $openAgain = new Transition('open', 'opened', 'opened');
        $close = new Transition('close', 'opened', 'closed');

        /** @var SplObjectStorage<Transition, array<array-key, mixed>> $metadata */
        $metadata = new SplObjectStorage();
        $metadata[$open] = ['refusals' => self::OPEN_REFUSALS];
        $metadata[$openAgain] = ['refusals' => self::OPEN_REFUSALS];
        $metadata[$close] = ['refusals' => self::CLOSE_REFUSALS];

        $dispatcher = new EventDispatcher();

        if ($guard !== null) {
            $dispatcher->addListener('workflow.door.guard.open', static function (GuardEvent $event) use (
                $guard,
            ): void {
                $event->addTransitionBlocker(new TransitionBlocker($guard, $guard));
            });
        }

        return new StateMachine(
            new Definition(
                ['closed', 'opened', 'locked', 'broken'],
                [$open, $openAgain, $close],
                'closed',
                new InMemoryMetadataStore([], [], $metadata),
            ),
            new MethodMarkingStore(singleState: true, property: 'status'),
            $dispatcher,
            'door',
        );
    }

    private static function subject(string $place): stdClass
    {
        $subject = new stdClass();
        $subject->status = $place;

        return $subject;
    }
}
