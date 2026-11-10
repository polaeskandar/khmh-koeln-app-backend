<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Library\Strings\Model\Text;
use PHPUnit\Framework\TestCase;

final class SourceLayoutTest extends TestCase
{
    public const array CONTEXTS = ['Identity', 'Learning', 'Meetings', 'Organization', 'People', 'Privacy'];

    private const array TOP_LEVEL_NAMESPACES = ['Context', 'Interface', 'Library', 'Platform', 'Workflow'];

    private const array CONTEXT_LAYERS = ['Application', 'Domain', 'Infrastructure'];

    private const array DOMAIN_KINDS = [
        'Attribute',
        'Entity',
        'Enum',
        'Exception',
        'Interface',
        'Query',
        'Repository',
        'ValueObject',
    ];

    private const array LIBRARY_KINDS = ['Client', 'Exception', 'Mapping', 'Model'];

    public function testSrcHoldsOnlyTheFiveTopLevelNamespaces(): void
    {
        self::assertSame(self::TOP_LEVEL_NAMESPACES, self::foldersIn('src'));
    }

    public function testTheContextsAreTheKnownOnes(): void
    {
        self::assertSame(self::CONTEXTS, self::foldersIn('src/Context'));
    }

    public function testTheWorkflowAndInterfaceAreasMirrorTheContexts(): void
    {
        foreach (['src/Workflow', 'src/Interface/Api', 'src/Interface/Console'] as $layer) {
            self::assertSame([], array_values(array_diff(self::foldersIn($layer), self::CONTEXTS)), $layer);
        }
    }

    public function testEveryContextHasAnApplicationADomainAndAnInfrastructure(): void
    {
        foreach (self::CONTEXTS as $context) {
            self::assertSame(self::CONTEXT_LAYERS, self::foldersIn('src/Context/' . $context), $context);
        }
    }

    public function testEveryDomainHoldsOnlyTheModel(): void
    {
        foreach (self::CONTEXTS as $context) {
            self::assertSame(
                [],
                array_values(array_diff(self::foldersIn('src/Context/' . $context . '/Domain'), self::DOMAIN_KINDS)),
                $context,
            );
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
