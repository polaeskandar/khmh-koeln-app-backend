<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Problem\Service\ProblemTranslator;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Logs;
use Monolog\Level;

final class FixedProblemTranslatorTest extends ApiTestCase
{
    public function testAUniqueViolationIsAConflictLoggedBelowError(): void
    {
        $this->jsonRequest('GET', '/api/test/problems/unique');

        $this->assertProblem(409, 'conflict');
        self::assertSame([], new Logs(self::getContainer())->atOrAbove(Level::Error));
    }
}
