<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Library\Strings\Model\Text;
use PHPUnit\Framework\TestCase;

final class SourceLayoutTest extends TestCase
{
    public const array CONTEXTS = ['Attendance', 'Identity', 'Learning', 'Meetings', 'Organization', 'People'];

    private const array TOP_LEVEL_NAMESPACES = ['Context', 'Interface', 'Library', 'Platform'];

    private const array CONTEXT_LAYERS = ['Domain', 'Infrastructure'];

    private const array LIBRARY_KINDS = ['Client', 'Exception', 'Model'];

    public function testSrcHoldsOnlyTheFourTopLevelNamespaces(): void
    {
        self::assertSame(self::TOP_LEVEL_NAMESPACES, self::foldersIn('src'));
    }

    public function testTheContextsAreTheKnownOnes(): void
    {
        self::assertSame(self::CONTEXTS, self::foldersIn('src/Context'));
    }

    public function testEveryContextHasADomainAndAnInfrastructure(): void
    {
        foreach (self::CONTEXTS as $context) {
            self::assertSame(self::CONTEXT_LAYERS, self::foldersIn('src/Context/' . $context), $context);
        }
    }

    public function testLibraryPackagesHoldOnlyClientsModelsAndExceptions(): void
    {
        foreach (self::foldersIn('src/Library') as $package) {
            self::assertSame(
                [],
                array_values(array_diff(self::foldersIn('src/Library/' . $package), self::LIBRARY_KINDS)),
                $package,
            );
        }
    }

    public function testEveryLibraryPackageIsGuardedAgainstTheOthers(): void
    {
        $guard = (string) file_get_contents(dirname(__DIR__, levels: 2) . '/mago.toml');

        foreach (self::foldersIn('src/Library') as $package) {
            self::assertStringContainsString(
                Text::format('dependency = "App\\\\Library\\\\%s\\\\**"', $package)->toString(),
                $guard,
                Text::format('mago.toml has no guard restriction for the %s package.', $package)->toString(),
            );
        }
    }

    /**
     * @return list<string>
     */
    private static function foldersIn(string $path): array
    {
        $folders = glob(dirname(__DIR__, levels: 2) . '/' . $path . '/*', GLOB_ONLYDIR);
        $names = array_map(basename(...), $folders === false ? [] : $folders);
        sort($names);

        return $names;
    }
}
