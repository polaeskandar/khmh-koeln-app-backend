<?php

declare(strict_types=1);

namespace App\Platform\OpenApi\Service;

use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;

final readonly class TypeNodes
{
    /**
     * @return list<string>
     */
    public static function literalsOf(TypeNode $type): array
    {
        $members = new ArrayCollection($type instanceof UnionTypeNode ? $type->types : [$type]);
        $values = Collections::mapPresent($members, self::literalOf(...));

        return $values->count() === $members->count() ? $values->getValues() : [];
    }

    private static function literalOf(TypeNode $type): ?string
    {
        return $type instanceof ConstTypeNode && $type->constExpr instanceof ConstExprStringNode
            ? $type->constExpr->value
            : null;
    }
}
