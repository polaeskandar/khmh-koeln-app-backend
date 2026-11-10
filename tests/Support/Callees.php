<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

final readonly class Callees
{
    public static function of(Node $call): ?ReflectionFunctionAbstract
    {
        return match (true) {
            $call instanceof New_ => self::method(EnclosingScope::className($call->class), '__construct'),
            $call instanceof Attribute => self::method($call->name->toString(), '__construct'),
            $call instanceof StaticCall => self::method(EnclosingScope::className($call->class), $call->name),
            $call instanceof MethodCall, $call instanceof NullsafeMethodCall => self::method(
                ExpressionClasses::of($call->var),
                $call->name,
            ),
            $call instanceof FuncCall && $call->name instanceof Expr => self::method(
                ExpressionClasses::of($call->name),
                '__invoke',
            ),
            $call instanceof FuncCall => self::function($call->name),
            default => null,
        };
    }

    private static function method(?string $class, Node|string $name): ?ReflectionMethod
    {
        $method = $name instanceof Identifier ? $name->toString() : $name;

        if ($class === null || !is_string($method) || !method_exists($class, $method)) {
            return null;
        }

        return ReflectionMethod::createFromMethodName($class . '::' . $method);
    }

    private static function function(Node $name): ?ReflectionFunction
    {
        if (!$name instanceof Name || !function_exists($name->toString())) {
            return null;
        }

        return new ReflectionFunction($name->toString());
    }
}
