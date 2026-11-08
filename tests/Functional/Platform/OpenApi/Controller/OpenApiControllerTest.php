<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\OpenApi\Controller;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\JsonBody;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;

final class OpenApiControllerTest extends ApiTestCase
{
    #[SkipDatabaseRollback]
    public function testServesTheCommittedDocumentWithoutSigningIn(): void
    {
        $response = $this->jsonRequest('GET', '/api/openapi.json');

        self::assertSame(200, $response->getStatusCode());

        $committed = JsonBody::asArray(
            json_decode(
                (string) file_get_contents(dirname(__DIR__, levels: 5) . '/docs/openapi.json'),
                associative: true,
                flags: JSON_THROW_ON_ERROR,
            ),
            'openapi.json',
        );

        self::assertSame(
            JsonBody::asArray($committed['paths'] ?? null, 'paths'),
            JsonBody::asArray($this->responseJson()['paths'] ?? null, 'paths'),
        );
    }
}
