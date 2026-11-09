<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class WorkflowLayoutTest extends TestCase
{
    private const string MODEL = 'Model';

    private const string WORKFLOW_SUFFIX = 'Workflow.php';

    private const string APPLICATION_SERVICES = '/src/Context/*/Application/Service/*.php';

    private const string VAGUE_SERVICE_NAME = '/(Service|Manager|Helper|Processor|Utils?)\.php$/';

    public function testAWorkflowAreaHoldsOnlyWorkflowsAndTheirModels(): void
    {
        $misplaced = array_filter(
            self::paths(self::root() . '/src/Workflow/*/*'),
            static fn (string $path): bool => is_dir($path)
                ? basename($path) !== self::MODEL
                : !str_ends_with($path, self::WORKFLOW_SUFFIX),
        );

        self::assertSame(
            [],
            array_values($misplaced),
            'src/Workflow/<Area> holds only <Verb><Subject>Workflow.php files and a Model folder.',
        );
    }

    /**
     * @param class-string $workflow
     */
    #[DataProvider('workflows')]
    public function testAWorkflowRunsOnlyThroughInvoke(string $workflow): void
    {
        $public = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            new ReflectionClass($workflow)->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        self::assertSame(['__invoke'], array_values(array_diff($public, ['__construct'])), $workflow);
    }

    /**
     * @param class-string $workflow
     */
    #[DataProvider('workflows')]
    public function testAWorkflowNeverCallsAnotherWorkflow(string $workflow): void
    {
        $source = (string) file_get_contents((string) new ReflectionClass($workflow)->getFileName());

        self::assertDoesNotMatchRegularExpression('/^use App\\\\Workflow\\\\\w+\\\\\w+Workflow;/m', $source, $workflow);
    }

    public function testApplicationServicesAreNamedAfterWhatTheyManage(): void
    {
        $vague = array_filter(
            self::paths(self::root() . self::APPLICATION_SERVICES),
            static fn (string $path): bool => preg_match(self::VAGUE_SERVICE_NAME, $path) === 1,
        );

        self::assertSame(
            [],
            array_values($vague),
            'Application services never end in Service, Manager, Helper, Processor or Util.',
        );
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function workflows(): iterable
    {
        foreach (self::paths(self::root() . '/src/Workflow/*/*' . self::WORKFLOW_SUFFIX) as $path) {
            $area = basename(dirname($path));
            $class = 'App\\Workflow\\' . $area . '\\' . basename($path, suffix: '.php');

            if (!class_exists($class)) {
                continue;
            }

            yield $class => [$class];
        }
    }

    /**
     * @return list<string>
     */
    private static function paths(string $pattern): array
    {
        $paths = glob($pattern);

        return $paths === false ? [] : $paths;
    }

    private static function root(): string
    {
        return dirname(__DIR__, levels: 2);
    }
}
