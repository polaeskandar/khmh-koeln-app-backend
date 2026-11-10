<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Strings\Model;

use App\Library\Strings\Model\ComposedForm;
use PHPUnit\Framework\TestCase;

final class ComposedFormTest extends TestCase
{
    public function testDecomposedLettersAreComposed(): void
    {
        self::assertSame("M\u{00FC}ller", ComposedForm::of("Mu\u{0308}ller"));
        self::assertSame("M\u{00FC}ller", ComposedForm::of("M\u{00FC}ller"));
        self::assertSame("\u{0623}\u{062D}\u{062F}", ComposedForm::of("\u{0627}\u{0654}\u{062D}\u{062F}"));
    }

    public function testTextThatIsNotUtf8IsLeftAsItIs(): void
    {
        self::assertSame("Chor \xFF", ComposedForm::of("Chor \xFF"));
    }
}
