<?php

declare(strict_types=1);

namespace App\Platform\OpenApi\Service;

use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\Text;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Model\Model;
use Nelmio\ApiDocBundle\Model\ModelRegistry;
use Nelmio\ApiDocBundle\ModelDescriber\ModelDescriberInterface;
use Nelmio\ApiDocBundle\ModelDescriber\ObjectModelDescriber;
use OpenApi\Annotations\Property;
use OpenApi\Annotations\Schema;
use OpenApi\Undefined;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\TypeInfo\Type\ObjectType;

#[AsDecorator('nelmio_api_doc.model_describers.object')]
final readonly class RequiredResponseFields implements ModelDescriberInterface, ModelRegistryAwareInterface
{
    private const string RESPONSE_NAMESPACES = '~\\\\(Response|Model)\\\\~';

    public function __construct(
        #[AutowireDecorated]
        private ObjectModelDescriber $inner,
    ) {
    }

    #[Override]
    public function describe(Model $model, Schema $schema): void
    {
        $this->inner->describe($model, $schema);
        $type = $model->getTypeInfo();

        if (!$type instanceof ObjectType || !self::isSent($type->getClassName())) {
            return;
        }

        if (Undefined::isDefault($schema->properties)) {
            return;
        }

        $properties = Collections::of($schema->properties);
        $schema->required = $properties->map(static fn (Property $field): string => $field->property)->getValues();
    }

    #[Override]
    public function supports(Model $model): bool
    {
        return $this->inner->supports($model);
    }

    #[Override]
    public function setModelRegistry(ModelRegistry $modelRegistry): void
    {
        $this->inner->setModelRegistry($modelRegistry);
    }

    private static function isSent(string $className): bool
    {
        return Text::of($className)->matches(self::RESPONSE_NAMESPACES);
    }
}
