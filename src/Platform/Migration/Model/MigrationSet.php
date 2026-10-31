<?php

declare(strict_types=1);

namespace App\Platform\Migration\Model;

use App\Library\Files\Model\Path;
use App\Library\Strings\Model\Text;
use Doctrine\Common\Collections\ArrayCollection;
use InvalidArgumentException;

final readonly class MigrationSet
{
    private function __construct(
        public string $context,
        public string $schema,
        public string $namespace,
        public string $directory,
    ) {
    }

    /**
     * @return ArrayCollection<int, self>
     */
    public static function all(string $projectDir): ArrayCollection
    {
        return Path::of($projectDir)->directories('src/Context/*/Infrastructure/Persistence/Migrations')->map(
            self::fromDirectory(...),
        );
    }

    public static function named(string $name, string $projectDir): self
    {
        $set = self::all($projectDir)
            ->filter(static fn (self $set): bool => $set->schema === $name)
            ->first();
        if ($set === false) {
            throw new InvalidArgumentException(Text::format('Unknown context "%s".', $name)->toString());
        }

        return $set;
    }

    public function versionTable(): string
    {
        return $this->schema . '_migration_versions';
    }

    private static function fromDirectory(Path $directory): self
    {
        $context = $directory->parent(levels: 3)->name();

        return new self(
            context: $context,
            schema: Text::of($context)->lower()->toString(),
            namespace: Text::format('App\\Context\\%s\\Infrastructure\\Persistence\\Migrations', $context)->toString(),
            directory: $directory->toString(),
        );
    }
}
