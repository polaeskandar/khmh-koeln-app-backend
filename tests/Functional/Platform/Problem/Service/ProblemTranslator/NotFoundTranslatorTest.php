<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Problem\Service\ProblemTranslator;

use App\Tests\Support\ApiTestCase;

final class NotFoundTranslatorTest extends ApiTestCase
{
    public function testANotFoundNamesItsThing(): void
    {
        $this->jsonRequest('GET', '/api/test/problems/not-found');

        $this->assertProblem(404, 'notFound.service');
    }
}
