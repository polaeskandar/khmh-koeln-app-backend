<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Library\Types\Model\Type;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;

final readonly class EnclosingScope
{
    public static function className(Node $class): ?string
    {
        if (!$class instanceof Name) {
            return null;
        }

        return match ($class->toLowerString()) {
            'self', 'static' => self::enclosingClass($class),
            'parent' => self::parentClass($class),
            default => $class->toString(),
        };
    }

    public static function variableClass(Variable $variable): ?string
    {
        if ($variable->name === 'this') {
            return self::enclosingClass($variable);
        }

        $parameter = array_find(
            self::parametersAround($variable),
            static fn (Param $parameter): bool => (
                $parameter->var instanceof Variable
                && $parameter->var->name === $variable->name
            ),
        );

        return self::typeName($parameter?->type);
    }

    public static function assignedValue(Variable $variable): ?Expr
    {
        $function = array_find(
            iterator_to_array(self::ancestors($variable), preserve_keys: false),
            static fn (Node $ancestor): bool => $ancestor instanceof FunctionLike,
        );

        $assignments = new NodeFinder()->find(
            $function instanceof FunctionLike ? $function->getStmts() ?? [] : [],
            static fn (Node $node): bool => (
                $node instanceof Assign
                && $node->var instanceof Variable
                && $node->var->name === $variable->name
                && $node->getEndFilePos() < $variable->getStartFilePos()
            ),
        );

        $last = array_last($assignments);

        return $last instanceof Assign ? $last->expr : null;
    }

    private static function typeName(?Node $type): ?string
    {
        return match (true) {
            $type instanceof NullableType => self::className($type->type),
            $type instanceof Name => self::className($type),
            default => null,
        };
    }

    /**
     * @return array<Param>
     */
    private static function parametersAround(Node $node): array
    {
        $functions = array_filter(
            iterator_to_array(self::ancestors($node), preserve_keys: false),
            static fn (Node $ancestor): bool => $ancestor instanceof FunctionLike,
        );

        return array_merge(...array_map(
            static fn (FunctionLike $function): array => $function->getParams(),
            $functions,
        ));
    }

    private static function enclosingClass(Node $node): ?string
    {
        $class = array_find(
            iterator_to_array(self::ancestors($node), preserve_keys: false),
            static fn (Node $ancestor): bool => $ancestor instanceof ClassLike,
        );

        return $class instanceof ClassLike ? $class->namespacedName?->toString() : null;
    }

    private static function parentClass(Node $node): ?string
    {
        $class = self::enclosingClass($node);
        $parent = $class === null ? false : get_parent_class($class);

        return $parent === false ? null : $parent;
    }

    /**
     * @return iterable<Node>
     */
    private static function ancestors(Node $node): iterable
    {
        if (!$node->hasAttribute('parent')) {
            return;
        }

        $parent = Type::of($node->getAttribute('parent'))->toInstanceOf(Node::class);

        yield $parent;
        yield from self::ancestors($parent);
    }
}
