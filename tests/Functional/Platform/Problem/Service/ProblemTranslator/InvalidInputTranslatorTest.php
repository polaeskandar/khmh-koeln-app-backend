<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Problem\Service\ProblemTranslator;

use App\Tests\Support\ApiTestCase;

final class InvalidInputTranslatorTest extends ApiTestCase
{
    public function testInvalidInputFromAServiceIsAFieldError(): void
    {
        $this->jsonRequest('GET', '/api/test/problems/invalid-input');

        $this->assertProblem(422, 'checkFields');
        $this->assertFieldError('name', 'services.nameTaken', ['name' => 'Choir']);
    }
}
