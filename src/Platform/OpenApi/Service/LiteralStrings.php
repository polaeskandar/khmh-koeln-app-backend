<?php

declare(strict_types=1);

namespace App\Platform\OpenApi\Service;

use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;
use Nelmio\ApiDocBundle\OpenApiPhp\Util;
use OpenApi\Annotations\Schema;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use ReflectionMethod;

final readonly class LiteralStrings
{
    public function __construct(
        private PhpDocReturnTypes $returns,
    ) {
    }

    public function describe(ReflectionMethod $method, Schema $schema): void
    {
        foreach (self::literalFieldsOf($this->returns->of($method)) as $field => $values) {
            Util::getProperty($schema, $field)->enum = $values;
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private static function literalFieldsOf(?TypeNode $type): array
    {
        if (!$type instanceof ArrayShapeNode) {
            return [];
        }

        return Collections::firstPerKey(new ArrayCollection($type->items), keyOf: self::keyOf(...))
            ->map(static fn (ArrayShapeItemNode $field): array => TypeNodes::literalsOf($field->valueType))
            ->filter(static fn (array $values): bool => $values !== [])
            ->toArray();
    }

    private static function keyOf(ArrayShapeItemNode $field): string
    {
        return $field->keyName instanceof ConstExprStringNode ? $field->keyName->value : (string) $field->keyName;
    }
}
