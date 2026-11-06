<?php

declare(strict_types=1);

namespace App\Platform\OpenApi\Service;

use App\Library\Strings\Model\Text;
use LogicException;
use Nelmio\ApiDocBundle\OpenApiPhp\Util;
use Nelmio\ApiDocBundle\TypeDescriber\StoppableTypeDescriberInterface;
use Nelmio\ApiDocBundle\TypeDescriber\TypeDescriberAwareInterface;
use Nelmio\ApiDocBundle\TypeDescriber\TypeDescriberInterface;
use OpenApi\Annotations\Schema;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ArrayShapeType;

/**
 * @implements StoppableTypeDescriberInterface<ArrayShapeType>
 */
#[AutoconfigureTag('nelmio_api_doc.type_describer', ['priority' => 0])]
final class ArrayShapes implements TypeDescriberAwareInterface, StoppableTypeDescriberInterface
{
    private ?TypeDescriberInterface $fields = null;

    #[Override]
    public function setDescriber(TypeDescriberInterface $describer): void
    {
        $this->fields = $describer;
    }

    /**
     * @param array<string, mixed> $context
     */
    #[Override]
    public function describe(Type $type, Schema $schema, array $context = []): void
    {
        $fields = $this->fields ?? throw new LogicException('The type describer chain sets the field describer.');
        $schema->type = 'object';
        $schema->required = [];

        foreach ($type->getShape() as $key => $field) {
            if ($field['optional']) {
                throw new LogicException(
                    Text::format('The response field "%s" is optional: send it as null instead.', $key)->toString(),
                );
            }

            $schema->required[] = (string) $key;
            $fields->describe($field['type'], Util::getProperty($schema, (string) $key), $context);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    #[Override]
    public function supports(Type $type, array $context = []): bool
    {
        return $type instanceof ArrayShapeType;
    }
}
