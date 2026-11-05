<?php

declare(strict_types=1);

namespace App\Tests\Unit\Platform\Logging\Model;

use App\Platform\Logging\Model\RedactedFailure;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class RedactedFailureTest extends TestCase
{
    public function testTheRedactedFailureKeepsWhereTheOriginalWasThrownWithoutItsArguments(): void
    {
        $original = $this->failureFor('a@example.org');
        $redacted = RedactedFailure::of($original);

        self::assertSame($original->getFile(), $redacted->getFile());
        self::assertSame($original->getLine(), $redacted->getLine());
        self::assertCount(count($original->getTrace()), $redacted->getTrace());
        self::assertStringContainsString('failureFor()', $redacted->getTraceAsString());
        self::assertStringNotContainsString('a@example.org', $redacted->getTraceAsString());
    }

    public function testEveryPreviousFailureKeepsItsOwnLocation(): void
    {
        $cause = $this->failureFor('b@example.org');
        $redacted = RedactedFailure::of(new RuntimeException('wrapped', previous: $cause));

        self::assertSame($cause->getLine(), $redacted->getPrevious()?->getLine());
    }

    private function failureFor(string $email): Throwable
    {
        return new RuntimeException('Lookup failed for ' . strlen($email) . ' characters.');
    }
}
