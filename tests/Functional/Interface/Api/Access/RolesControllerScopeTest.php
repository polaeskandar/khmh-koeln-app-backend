<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Access;

use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;

final class RolesControllerScopeTest extends ApiTestCase
{
    private const array READ_SERVICES = ['action' => 'read', 'resource' => 'services'];

    public function testARoleHoldsChurchWideUnlessItIsLimitedToTheAssignedGroups(): void
    {
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', '/api/roles', [
            'name' => 'Choir helper',
            'scope' => 'assignedGroups',
            'permissions' => [self::READ_SERVICES],
        ]);

        self::assertResponseStatusCodeSame(201);
        $id = $this->createdId();
        $this->jsonRequest('GET', '/api/roles');
        $this->assertJsonPath('items.0.name', 'Administrator');
        $this->assertJsonPath('items.0.scope', 'church');
        $this->assertJsonPath('items.1.name', 'Choir helper');
        $this->assertJsonPath('items.1.scope', 'assignedGroups');

        $this->jsonRequest('PUT', '/api/roles/' . $id, [
            'name' => 'Choir helper',
            'permissions' => [self::READ_SERVICES],
        ]);

        $this->assertJsonPath('scope', 'assignedGroups');

        $this->jsonRequest('PUT', '/api/roles/' . $id, [
            'name' => 'Choir helper',
            'scope' => 'church',
            'permissions' => [self::READ_SERVICES],
        ]);

        $this->assertJsonPath('scope', 'church');

        $this->jsonRequest('PUT', '/api/roles/' . $id, [
            'name' => '',
            'scope' => 'everywhere',
            'permissions' => [self::READ_SERVICES],
        ]);

        $this->assertFieldError('name', 'roles.nameRequired');
        $this->assertFieldError('scope', 'validation.invalidChoice');
        $this->jsonRequest('POST', '/api/roles', ['name' => 'Usher', 'scope' => 3, 'permissions' => []]);
        $this->assertFieldError('scope', 'validation.invalidType');
        $this->jsonRequest('GET', '/api/roles/' . $id);
        $this->assertJsonPath('scope', 'church');
    }

    private function createdId(): string
    {
        return Type::of($this->responseJson()['id'] ?? null)->toString();
    }
}
