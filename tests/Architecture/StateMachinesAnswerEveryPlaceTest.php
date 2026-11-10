<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Tests\Support\JsonBody;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Yaml\Yaml;

final class StateMachinesAnswerEveryPlaceTest extends KernelTestCase
{
    #[DataProvider('machines')]
    public function testEveryMachineIsAStateMachineNamedAfterALifecycle(string $name): void
    {
        self::assertStringEndsWith('_lifecycle', $name);
        self::assertTrue(self::getContainer()->has('state_machine.' . $name));
        self::assertSame($name, self::machine($name)->getName());
    }

    #[DataProvider('machines')]
    public function testEveryTransitionStartsFromOrRefusesEachPlaceExactlyOnce(string $name): void
    {
        $machine = self::machine($name);
        $places = array_values($machine->getDefinition()->getPlaces());
        sort($places);

        foreach (self::transitionNames($machine) as $transition) {
            self::assertSame(
                $places,
                self::answeredPlaces($machine, $transition),
                sprintf('%s of %s must start from or refuse each place once.', $transition, $name),
            );
        }
    }

    #[DataProvider('machines')]
    public function testEveryRefusalIsACodeOfTheCatalog(string $name): void
    {
        $machine = self::machine($name);
        $catalog = (string) file_get_contents(dirname(__DIR__, levels: 2) . '/docs/api.md');

        foreach (self::transitionNames($machine) as $transition) {
            foreach (self::refusals($machine, $transition) as $code) {
                self::assertStringContainsString(
                    '| `' . $code . '` |',
                    $catalog,
                    sprintf('%s of %s refuses with an unknown code.', $transition, $name),
                );
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function machines(): iterable
    {
        $workflows = JsonBody::asArray(
            JsonBody::valueAt(
                Yaml::parseFile(dirname(__DIR__, levels: 2) . '/config/packages/workflow.yaml'),
                ['framework', 'workflows'],
                'framework.workflows',
            ),
            'framework.workflows',
        );

        foreach (array_keys($workflows) as $name) {
            yield (string) $name => [(string) $name];
        }
    }

    private static function machine(string $name): WorkflowInterface
    {
        $machine = self::getContainer()->get('state_machine.' . $name);
        self::assertInstanceOf(WorkflowInterface::class, $machine);

        return $machine;
    }

    /**
     * @return list<string>
     */
    private static function transitionNames(WorkflowInterface $machine): array
    {
        $names = array_map(
            static fn (Transition $transition): string => $transition->getName(),
            $machine->getDefinition()->getTransitions(),
        );

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    private static function answeredPlaces(WorkflowInterface $machine, string $transition): array
    {
        $answered = array_merge(
            self::startPlaces($machine, $transition),
            array_keys(self::refusals($machine, $transition)),
        );

        sort($answered);

        return $answered;
    }

    /**
     * @return list<string>
     */
    private static function startPlaces(WorkflowInterface $machine, string $transition): array
    {
        $named = array_filter(
            $machine->getDefinition()->getTransitions(),
            static fn (Transition $candidate): bool => $candidate->getName() === $transition,
        );

        return array_values(array_merge(...array_map(
            static fn (Transition $named): array => $named->getFroms(),
            array_values($named),
        )));
    }

    /**
     * @return array<string, string>
     */
    private static function refusals(WorkflowInterface $machine, string $transition): array
    {
        $named = array_find(
            $machine->getDefinition()->getTransitions(),
            static fn (Transition $candidate): bool => $candidate->getName() === $transition,
        );

        self::assertInstanceOf(Transition::class, $named);

        $refusals = JsonBody::asArray(
            $machine->getMetadataStore()->getTransitionMetadata($named)['refusals'] ?? [],
            'refusals',
        );

        return array_combine(array_map(strval(...), array_keys($refusals)), array_map(strval(...), $refusals));
    }
}
