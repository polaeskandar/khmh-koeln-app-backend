<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Tests\Support\SourceTypes;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Traversable;

final class NamingConventionsTest extends TestCase
{
    private const string LOOKUP_LAYERS = '/^App\\\\Context\\\\\w+\\\\(Application\\\\Service|Domain\\\\Repository|Infrastructure\\\\Persistence\\\\Repository)\\\\/';

    private const string GET = '/^get($|[A-Z])/';

    private const string FIND = '/^find($|[A-Z])/';

    private const string WORKFLOW = '/^App\\\\Workflow\\\\\w+\\\\\w+Workflow$/';

    private const string WORKFLOW_MODEL = '/^App\\\\Workflow\\\\\w+\\\\Model\\\\/';

    private const string WORKFLOW_INPUT = '/(Choice|Filter)$/';

    public function testEveryInterfaceEndsInInterface(): void
    {
        $unsuffixed = array_filter(
            SourceTypes::all(),
            static fn (ReflectionClass $type): bool => $type->isInterface()
            && !str_ends_with($type->getShortName(), 'Interface'),
        );

        self::assertSame([], self::namesOf($unsuffixed), 'docs/NAMING_CONVENTIONS.md §2: interfaces end in Interface.');
    }

    public function testOfIsOnlyEverAStaticNamedConstructor(): void
    {
        $instanceOf = array_filter(
            SourceTypes::publicMethods(),
            static fn (ReflectionMethod $method): bool => $method->getName() === 'of' && !$method->isStatic(),
        );

        self::assertSame(
            [],
            self::namesOf($instanceOf),
            'docs/NAMING_CONVENTIONS.md §3: an instance method is not of().',
        );
    }

    public function testGetFindsWhatMustExistAndFindMayFindNothing(): void
    {
        $lookups = array_filter(
            SourceTypes::publicMethods(),
            static fn (ReflectionMethod $method): bool => preg_match(self::LOOKUP_LAYERS, $method->class) === 1,
        );

        $nullableGets = array_filter(
            $lookups,
            static fn (ReflectionMethod $method): bool => (
                preg_match(self::GET, $method->getName()) === 1
                && $method->getReturnType()?->allowsNull() === true
            ),
        );

        $certainFinds = array_filter(
            $lookups,
            static fn (ReflectionMethod $method): bool => (
                preg_match(self::FIND, $method->getName()) === 1
                && !self::mayFindNothing($method)
            ),
        );

        self::assertSame([], self::namesOf($nullableGets), 'docs/NAMING_CONVENTIONS.md §3: get…() never returns null.');
        self::assertSame([], self::namesOf($certainFinds), 'docs/NAMING_CONVENTIONS.md §3: find…() may find nothing.');
    }

    public function testWorkflowInputsAreChoicesOrFilters(): void
    {
        $workflows = array_filter(
            SourceTypes::all(),
            static fn (ReflectionClass $type): bool => preg_match(self::WORKFLOW, $type->getName()) === 1,
        );

        $inputs = array_merge(...array_values(array_map(static fn (ReflectionClass $workflow): array => $workflow
            ->getMethod('__invoke')
            ->getParameters(), $workflows)));

        $misnamed = array_values(array_unique(array_filter(
            array_map(self::modelTypeOf(...), $inputs),
            static fn (?string $model): bool => $model !== null && preg_match(self::WORKFLOW_INPUT, $model) !== 1,
        )));

        self::assertSame([], $misnamed, 'docs/NAMING_CONVENTIONS.md §2: workflow inputs end in Choice or Filter.');
    }

    private static function mayFindNothing(ReflectionMethod $method): bool
    {
        $type = $method->getReturnType();

        if ($type === null || $type->allowsNull()) {
            return true;
        }

        return (
            $type instanceof ReflectionNamedType
            && (
                in_array($type->getName(), ['array', 'iterable'], strict: true)
                || is_a($type->getName(), Traversable::class, allow_string: true)
            )
        );
    }

    private static function modelTypeOf(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType && preg_match(self::WORKFLOW_MODEL, $type->getName()) === 1
            ? $type->getName()
            : null;
    }

    /**
     * @param array<ReflectionClass<object>|ReflectionMethod> $reflections
     *
     * @return list<string>
     */
    private static function namesOf(array $reflections): array
    {
        return array_values(array_map(static fn (ReflectionClass|ReflectionMethod $reflection): string => $reflection
            instanceof ReflectionMethod
                ? $reflection->class . '::' . $reflection->getName()
                : $reflection->getName(), $reflections));
    }
}
