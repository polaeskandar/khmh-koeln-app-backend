<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Problem\Service\ProblemTranslator;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Logs;

final class RuleViolationTranslatorTest extends ApiTestCase
{
    public function testARuleViolationCarriesItsParamsAndExtensionsWithoutLoggingThem(): void
    {
        $this->jsonRequest('GET', '/api/test/problems/rule');

        $this->assertProblem(409, 'services.hasHistory', ['name' => 'Choir', 'count' => 12]);
        $this->assertJsonPath('candidates.0.firstName', 'Mina');
        $logs = new Logs(self::getContainer());
        self::assertSame([], $logs->containing('Choir'));
        self::assertSame([], $logs->containing('Mina'));
    }
}
