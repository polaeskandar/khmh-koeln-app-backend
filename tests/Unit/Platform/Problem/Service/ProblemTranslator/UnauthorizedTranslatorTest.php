<?php

declare(strict_types=1);

namespace App\Tests\Unit\Platform\Problem\Service\ProblemTranslator;

use App\Platform\Problem\Service\ProblemTranslator\UnauthorizedTranslator;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class UnauthorizedTranslatorTest extends TestCase
{
    public function testACodedRefusalKeepsItsCode(): void
    {
        $problem = new UnauthorizedTranslator()->translate(new UnauthorizedHttpException(
            'Cookie',
            'auth.invalidCredentials',
        ));

        self::assertSame([401, 'auth.invalidCredentials'], [$problem->status, $problem->code]);
    }

    public function testAnUncodedRefusalIsUnauthenticated(): void
    {
        $translator = new UnauthorizedTranslator();

        self::assertSame(
            'auth.unauthenticated',
            $translator->translate(new UnauthorizedHttpException('Cookie', 'No.'))->code,
        );

        self::assertFalse($translator->supports(new RuntimeException('auth.invalidCredentials')));
    }
}
