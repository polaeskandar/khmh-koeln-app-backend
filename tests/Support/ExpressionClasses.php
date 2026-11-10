<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;

final readonly class ExpressionClasses
{
    /**
     * @return class-string|null
     */
    public static function of(Expr $expression): ?string
    {
        $class = self::declaredClass($expression);

        return $class !== null && (class_exists($class) || interface_exists($class)) ? $class : null;
    }

    private static function declaredClass(Expr $expression): ?string
    {
        return match (true) {
            $expression instanceof Variable => self::variableClass($expression),
            $expression instanceof PropertyFetch, $expression instanceof NullsafePropertyFetch => self::propertyClass(
                self::of($expression->var),
                $expression->name,
            ),
            $expression instanceof New_ => EnclosingScope::className($expression->class),
            $expression instanceof CallLike => self::returnedClass(Callees::of($expression)),
            default => null,
        };
    }

    private static function variableClass(Variable $variable): ?string
    {
        $declared = EnclosingScope::variableClass($variable);

        if ($declared !== null) {
            return $declared;
        }

        $value = EnclosingScope::assignedValue($variable);

        return $value === null ? null : self::of($value);
    }

    /**
     * @param class-string|null $class
     */
    private static function propertyClass(?string $class, Node $name): ?string
    {
        if ($class === null || !$name instanceof Identifier || !property_exists($class, $name->toString())) {
            return null;
        }

        return self::namedClass(new ReflectionProperty($class, $name->toString())->getType(), $class);
    }

    private static function returnedClass(?ReflectionFunctionAbstract $callee): ?string
    {
        $class = $callee instanceof ReflectionMethod ? $callee->getDeclaringClass()->getName() : null;

        return self::namedClass($callee?->getReturnType() ?? $callee?->getTentativeReturnType(), $class);
    }

    private static function namedClass(?ReflectionType $type, ?string $self): ?string
    {
        if (!$type instanceof ReflectionNamedType) {
            return null;
        }

        return match ($type->getName()) {
            'self', 'static' => $self,
            default => $type->getName(),
        };
    }
}
