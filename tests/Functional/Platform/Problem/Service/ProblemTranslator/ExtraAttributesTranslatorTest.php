<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Problem\Service\ProblemTranslator;

use App\Tests\Support\ApiTestCase;

final class ExtraAttributesTranslatorTest extends ApiTestCase
{
    public function testAnUnexpectedFieldIsReported(): void
    {
        $this->jsonRequest('POST', '/api/test/problems/payload', ['name' => 'Choir', 'colour' => 'red']);

        $this->assertProblem(422, 'checkFields');
        $this->assertFieldError('colour', 'validation.unexpectedField');
    }
}
