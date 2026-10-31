<?php

declare(strict_types=1);

namespace App\Platform\Migration\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Schema\AbstractNamedObject;
use Doctrine\DBAL\Schema\Name;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Provider\SchemaProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;

final readonly class ContextSchemaProvider implements SchemaProvider
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $schema,
    ) {
    }

    #[Override]
    public function createSchema(): Schema
    {
        $mapped = new SchemaTool($this->entityManager)->getSchemaFromMetadata(
            $this->entityManager->getMetadataFactory()->getAllMetadata(),
        );

        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
        $database = $schemaManager->introspectSchema();

        return new Schema(
            [
                ...new ArrayCollection($mapped->getTables())->filter($this->isInTheContext(...)),
                ...new ArrayCollection($database->getTables())->filter($this->isOutsideTheContext(...)),
            ],
            [
                ...new ArrayCollection($mapped->getSequences())->filter($this->isInTheContext(...)),
                ...new ArrayCollection($database->getSequences())->filter($this->isOutsideTheContext(...)),
            ],
            $schemaManager->createSchemaConfig(),
        );
    }

    /**
     * @param AbstractNamedObject<covariant Name> $asset
     */
    private function isInTheContext(AbstractNamedObject $asset): bool
    {
        $name = $asset->getObjectName();

        return $name instanceof OptionallyQualifiedName && $name->getQualifier()?->getValue() === $this->schema;
    }

    /**
     * @param AbstractNamedObject<covariant Name> $asset
     */
    private function isOutsideTheContext(AbstractNamedObject $asset): bool
    {
        return !$this->isInTheContext($asset);
    }
}
