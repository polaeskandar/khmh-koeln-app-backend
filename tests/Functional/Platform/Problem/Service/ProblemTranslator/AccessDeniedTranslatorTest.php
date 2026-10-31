<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Problem\Service\ProblemTranslator;

use App\Tests\Support\ApiTestCase;

final class AccessDeniedTranslatorTest extends ApiTestCase
{
    public function testACodedDenialKeepsItsCode(): void
    {
        $this->jsonRequest('GET', '/api/test/problems/forbidden');

        $this->assertProblem(403, 'services.adminOnlyCreate');
    }

    public function testAnUncodedDenialIsForbidden(): void
    {
        $this->jsonRequest('GET', '/api/test/problems/denied');

        $this->assertProblem(403, 'forbidden');
    }
}
