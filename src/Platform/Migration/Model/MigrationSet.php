<?php

declare(strict_types=1);

namespace App\Platform\Migration\Model;

use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\Text;
use Doctrine\Common\Collections\ArrayCollection;
use InvalidArgumentException;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final readonly class MigrationSet
{
    private const string CONTEXTS = 'src/Context';

    private const string MIGRATIONS = '#^(\w+)/Infrastructure/Persistence/Migrations$#';

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
        $directories = new Finder()
            ->directories()
            ->in(Path::join($projectDir, self::CONTEXTS))
            ->path(self::MIGRATIONS)
            ->sortByName();

        return new ArrayCollection(Collections::of($directories)->getValues())->map(self::fromDirectory(...));
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

    private static function fromDirectory(SplFileInfo $directory): self
    {
        $context = Text::of($directory->getRelativePathname())->replaceMatches(self::MIGRATIONS, '$1')->toString();

        return new self(
            context: $context,
            schema: Text::of($context)->lower()->toString(),
            namespace: Text::format('App\\Context\\%s\\Infrastructure\\Persistence\\Migrations', $context)->toString(),
            directory: $directory->getPathname(),
        );
    }
}
