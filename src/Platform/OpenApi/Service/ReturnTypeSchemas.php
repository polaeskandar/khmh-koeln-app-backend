<?php

declare(strict_types=1);

namespace App\Platform\OpenApi\Service;

use LogicException;
use Nelmio\ApiDocBundle\Model\Model;
use Nelmio\ApiDocBundle\Model\ModelRegistry;
use Nelmio\ApiDocBundle\TypeDescriber\ChainDescriber;
use OpenApi\Annotations\Schema;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

final readonly class ReturnTypeSchemas
{
    public function __construct(
        #[Autowire(service: 'nelmio_api_doc.type_describer.chain')]
        private ChainDescriber $types,
        #[Autowire(service: 'type_info.resolver')]
        private TypeResolverInterface $resolver,
    ) {
    }

    /**
     * @return ReflectionClass<object>|null
     */
    public static function reflectionOf(Model $model): ?ReflectionClass
    {
        $type = $model->getTypeInfo();

        return $type instanceof ObjectType ? new ReflectionClass($type->getClassName()) : null;
    }

    public function describe(Model $model, string $method, Schema $schema, ?ModelRegistry $registry): void
    {
        $type = $model->getTypeInfo();

        if (!$type instanceof ObjectType) {
            throw new LogicException('Only classes describe themselves through a return type.');
        }

        $this->types->setModelRegistry($registry ?? throw new LogicException('nelmio sets the model registry.'));
        $this->types->describe($this->resolver->resolve(new ReflectionMethod($type->getClassName(), $method)), $schema);
    }
}
