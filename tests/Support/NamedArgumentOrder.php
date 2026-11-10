<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\CallLike;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

final readonly class NamedArgumentOrder
{
    /**
     * @return list<string>
     */
    public static function violationsIn(string $source, string $file): array
    {
        $calls = self::callsNamingSeveralArguments($source);
        $violations = array_map(static fn (Node $call): ?string => self::violation($call, $file), $calls);

        return array_values(array_filter($violations, is_string(...)));
    }

    /**
     * @return list<Node>
     */
    private static function callsNamingSeveralArguments(string $source): array
    {
        $nodes = new ParserFactory()
            ->createForNewestSupportedVersion()
            ->parse($source) ?? [];

        new NodeTraverser(new NameResolver(), new ParentConnectingVisitor())->traverse($nodes);

        $calls = new NodeFinder()->find($nodes, static fn (Node $node): bool => count(self::argumentNames($node)) > 1);

        return array_values($calls);
    }

    private static function violation(Node $call, string $file): ?string
    {
        $callee = Callees::of($call);

        if ($callee === null) {
            return null;
        }

        $parameters = array_map(
            static fn (ReflectionParameter $parameter): string => $parameter->getName(),
            $callee->getParameters(),
        );

        $passed = array_values(array_intersect(self::argumentNames($call), $parameters));
        $declared = array_values(array_intersect($parameters, $passed));

        if ($passed === $declared) {
            return null;
        }

        return sprintf(
            '%s:%d %s() takes %s; the call passes %s.',
            $file,
            $call->getStartLine(),
            self::calleeName($callee),
            implode(', ', $declared),
            implode(', ', $passed),
        );
    }

    /**
     * @return list<string>
     */
    private static function argumentNames(Node $node): array
    {
        $arguments = match (true) {
            $node instanceof CallLike => $node->getRawArgs(),
            $node instanceof Attribute => $node->args,
            default => [],
        };

        return array_values(array_filter(array_map(self::argumentName(...), $arguments), is_string(...)));
    }

    private static function argumentName(Node $argument): ?string
    {
        return $argument instanceof Arg ? $argument->name?->toString() : null;
    }

    private static function calleeName(ReflectionFunctionAbstract $callee): string
    {
        return $callee instanceof ReflectionMethod
            ? $callee->getDeclaringClass()->getName() . '::' . $callee->getName()
            : $callee->getName();
    }
}
