<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Tests\Support\NamedArgumentOrder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class NamedArgumentsFollowParameterOrderTest extends TestCase
{
    private const array FOLDERS = ['src', 'tests', 'tools'];

    public function testEveryCallPassesItsNamedArgumentsInTheOrderOfTheParameters(): void
    {
        $root = dirname(__DIR__, levels: 2);

        $files = Finder::create()
            ->files()
            ->name('*.php')
            ->in(array_map(static fn (string $folder): string => $root . '/' . $folder, self::FOLDERS));

        $violations = array_map(
            static fn (SplFileInfo $file): array => NamedArgumentOrder::violationsIn($file->getContents(), substr(
                $file->getPathname(),
                strlen($root) + 1,
            )),
            iterator_to_array($files, preserve_keys: false),
        );

        self::assertSame(
            [],
            array_merge(...$violations),
            'Pass named arguments in the order the callee declares its parameters.',
        );
    }

    #[DataProvider('callsOutOfOrder')]
    public function testACallOutOfOrderIsReported(string $source, string $violation): void
    {
        self::assertSame([$violation], NamedArgumentOrder::violationsIn('<?php ' . $source, 'Example.php'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function callsOutOfOrder(): iterable
    {
        yield 'function' => [
            'str_pad("7", pad_type: STR_PAD_LEFT, length: 2);',
            'Example.php:1 str_pad() takes length, pad_type; the call passes pad_type, length.',
        ];

        yield 'constructor' => [
            'new ArrayIterator(flags: 0, array: []);',
            'Example.php:1 ArrayIterator::__construct() takes array, flags; the call passes flags, array.',
        ];

        yield 'static method' => [
            'DateTime::createFromFormat(datetime: "2026-11-10", format: "Y-m-d");',
            'Example.php:1 DateTime::createFromFormat() takes format, datetime; the call passes datetime, format.',
        ];

        yield 'method of a typed parameter' => [
            'fn (DateTime $day) => $day->setDate(month: 11, year: 2026, day: 10);',
            'Example.php:1 DateTime::setDate() takes year, month, day; the call passes month, year, day.',
        ];

        yield 'method of an assigned variable' => [
            'function f(): void { $day = new DateTime(); $day->setTime(minute: 0, hour: 9); }',
            'Example.php:1 DateTime::setTime() takes hour, minute; the call passes minute, hour.',
        ];

        yield 'method of a returned object' => [
            'new DateTime()->modify("+1 day")->setTime(minute: 0, hour: 9);',
            'Example.php:1 DateTime::setTime() takes hour, minute; the call passes minute, hour.',
        ];

        yield 'attribute' => [
            '#[Deprecated(since: "1.0", message: "Use g().")] function f(): void {}',
            'Example.php:1 Deprecated::__construct() takes message, since; the call passes since, message.',
        ];
    }

    public function testACallInOrderAndOneTheTestCannotResolveAreAccepted(): void
    {
        $source = '<?php str_pad("7", length: 2, pad_type: STR_PAD_LEFT); $unknown->call(b: 1, a: 2);';

        self::assertSame([], NamedArgumentOrder::violationsIn($source, 'Example.php'));
    }
}
