<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Privacy;

use App\Context\Privacy\Domain\Enum\RequestKind;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Interface\Api\Privacy\ErasureFixture;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class DataRequestsControllerConflictTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testEachConflictHasItsCode(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $fixture = new ErasureFixture(self::getContainer());
        [$kiro] = $fixture->kiro();
        $done = $fixture->request(RequestKind::Erasure, $kiro);
        $again = $fixture->request(RequestKind::Erasure, $kiro);
        $copy = $fixture->request(RequestKind::Copy, new Rosters(self::getContainer())->person('Mina', 'Wassef'));
        $this->signInWithRoles('admin@example.org', 'administrator');
        $this->jsonRequest('POST', '/api/data-requests/' . $done . '/erase', ['clearNotes' => false]);

        $this->jsonRequest('POST', '/api/data-requests/' . $done . '/erase', ['clearNotes' => false]);
        $this->assertProblem(409, 'privacy.notOpen');

        $this->jsonRequest('POST', '/api/data-requests/' . $again . '/erase', ['clearNotes' => false]);
        $this->assertProblem(409, 'privacy.subjectGone');

        $this->jsonRequest('POST', '/api/data-requests/' . $copy . '/erase', ['clearNotes' => false]);
        $this->assertProblem(409, 'privacy.notErasure');

        $this->jsonRequest('POST', '/api/data-requests/' . $copy . '/erase', []);
        $this->assertFieldError('clearNotes', 'validation.invalidType');

        $this->jsonRequest('POST', '/api/data-requests/not-a-request/erase', ['clearNotes' => false]);
        $this->assertProblem(404, 'notFound.request');

        $this->jsonRequest('GET', '/api/data-requests/not-a-request/erasure');
        $this->assertProblem(404, 'notFound.request');
    }

    public function testAnAdministratorCannotEraseTheirOwnAccount(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $admin = $this->signInWithRoles('admin@example.org', 'administrator');
        $id = new ErasureFixture(self::getContainer())->request(RequestKind::Erasure, null, $admin);

        $this->jsonRequest('GET', '/api/data-requests/' . $id . '/erasure');
        $this->assertProblem(409, 'privacy.ownAccount');

        $this->jsonRequest('POST', '/api/data-requests/' . $id . '/erase', ['clearNotes' => false]);
        $this->assertProblem(409, 'privacy.ownAccount');
    }
}
