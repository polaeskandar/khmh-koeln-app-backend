<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Strings\Model;

use App\Library\Strings\Model\SearchWords;
use PHPUnit\Framework\TestCase;

final class SearchWordsTest extends TestCase
{
    public function testEveryWordMustBeFoundIgnoringCaseAccentsAndSpaces(): void
    {
        $words = SearchWords::of('  LUKE   samar ');

        self::assertTrue($words->areAllIn('The Good Samaritan Luke 10:25-37'));
        self::assertFalse($words->areAllIn('The Good Samaritan'));
        self::assertTrue(SearchWords::of('jurgen')->areAllIn('Jürgen Müller'));
        self::assertTrue(SearchWords::of(null)->areAllIn('anything'));
    }
}
