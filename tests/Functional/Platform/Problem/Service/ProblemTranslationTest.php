<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Problem\Service;

use App\Tests\Support\ApiTestCase;

final class ProblemTranslationTest extends ApiTestCase
{
    public function testTheExceptionThatWasThrownWinsOverTheOneItWraps(): void
    {
        $this->jsonRequest('GET', '/api/test/problems/rethrown');

        $this->assertProblem(403, 'services.adminOnlyCreate');
    }

    public function testAWrappedExceptionIsFoundWhenTheWrapperHasNoProblem(): void
    {
        $this->jsonRequest('POST', '/api/test/problems/payload');

        $this->assertProblem(422, 'checkFields');
    }
}
