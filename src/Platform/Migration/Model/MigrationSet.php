<?php

declare(strict_types=1);

namespace App\Platform\Migration\Model;

use App\Library\Strings\Model\Text;
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
     * @return list<self>
     */
    public static function all(string $projectDir): array
    {
        $directories = glob($projectDir . '/src/Context/*/Infrastructure/Persistence/Migrations', GLOB_ONLYDIR);

        return array_map(self::fromDirectory(...), $directories === false ? [] : $directories);
    }

    public static function named(string $name, string $projectDir): self
    {
        foreach (self::all($projectDir) as $set) {
            if ($set->schema === $name) {
                return $set;
            }
        }

        throw new InvalidArgumentException(Text::format('Unknown context "%s".', $name)->toString());
    }

    public function versionTable(): string
    {
        return $this->schema . '_migration_versions';
    }

    private static function fromDirectory(string $directory): self
    {
        $context = basename(dirname($directory, levels: 3));

        return new self(
            $context,
            Text::of($context)->lower()->toString(),
            Text::format('App\\Context\\%s\\Infrastructure\\Persistence\\Migrations', $context)->toString(),
            $directory,
        );
    }
}
