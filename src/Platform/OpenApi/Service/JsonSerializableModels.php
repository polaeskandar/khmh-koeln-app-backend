<?php

declare(strict_types=1);

namespace App\Platform\OpenApi\Service;

use JsonSerializable;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Model\Model;
use Nelmio\ApiDocBundle\Model\ModelRegistry;
use Nelmio\ApiDocBundle\ModelDescriber\ModelDescriberInterface;
use OpenApi\Annotations\Schema;
use Override;
use ReflectionAttribute;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('nelmio_api_doc.model_describer', ['priority' => 500])]
final class JsonSerializableModels implements ModelDescriberInterface, ModelRegistryAwareInterface
{
    private ?ModelRegistry $registry = null;

    public function __construct(
        private readonly ReturnTypeSchemas $schemas,
    ) {
    }

    #[Override]
    public function setModelRegistry(ModelRegistry $modelRegistry): void
    {
        $this->registry = $modelRegistry;
    }

    #[Override]
    public function describe(Model $model, Schema $schema): void
    {
        $this->schemas->describe($model, 'jsonSerialize', $schema, $this->registry);
    }

    #[Override]
    public function supports(Model $model): bool
    {
        $reflection = ReturnTypeSchemas::reflectionOf($model);

        if ($reflection === null || !$reflection->implementsInterface(JsonSerializable::class)) {
            return false;
        }

        $ownSchema = $reflection->getAttributes(Schema::class, ReflectionAttribute::IS_INSTANCEOF) !== [];

        return !$reflection->isInterface() && !$ownSchema;
    }
}
