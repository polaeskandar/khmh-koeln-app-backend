<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Problem\Service\ProblemTranslator;

use App\Tests\Support\ApiTestCase;

final class NotFoundHttpTranslatorTest extends ApiTestCase
{
    public function testAnUnknownRouteIsNotFound(): void
    {
        $this->jsonRequest('GET', '/api/does-not-exist');

        $this->assertProblem(404, 'notFound.route');
    }

    public function testACodedNotFoundKeepsItsCode(): void
    {
        $this->jsonRequest('GET', '/api/test/problems/entity-not-found');

        $this->assertProblem(404, 'notFound.service');
    }
}
