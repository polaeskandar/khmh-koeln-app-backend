<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Privacy;

use App\Interface\Api\Privacy\Response\AccountDataResponse;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\JsonBody;
use App\Workflow\Privacy\ExportAccountDataWorkflow;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Serializer\SerializerInterface;

final class OwnDataControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testEveryCallNeedsASession(): void
    {
        foreach ([
            ['GET',  '/api/me/personal-data'],
            ['GET',  '/api/me/erasure-request'],
            ['POST', '/api/me/erasure-request'],
        ] as [
            $method,
            $path,
        ]) {
            $this->jsonRequest($method, $path);
            $this->assertProblem(401, 'auth.unauthenticated');
        }
    }

    public function testAServantDownloadsTheirOwnExport(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        new Families(self::getContainer())->account('daniel@example.org', [], 'servant');
        $servant = $this->signInWithRoles('christine@example.org', 'servant');

        $this->jsonRequest('GET', '/api/me/personal-data');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=khmk-my-data.json');
        $this->assertJsonPath('account.email', 'christine@example.org');
        self::assertSame($this->exportOf($servant), $this->responseJson());
    }

    public function testAParentGetsTheChildrensNamesButNoAttendance(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $mina = new Rosters(self::getContainer())->person('Mina', 'Hanna');
        $parent = $this->signInWithRoles('hoda@example.org', 'parent');
        new Families(self::getContainer())->link($parent, $mina);

        $this->jsonRequest('GET', '/api/me/personal-data');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('children', [['name' => 'Mina Hanna', 'linkStatus' => 'approved']]);

        self::assertSame(
            ['kind', 'exportedAt', 'account', 'children', 'homeworkReports'],
            array_keys($this->responseJson()),
        );

        self::assertSame($this->exportOf($parent), $this->responseJson());
    }

    public function testAnAccountAsksOnceForErasure(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $this->signInWithRoles('hoda@example.org', 'parent');

        $this->jsonRequest('GET', '/api/me/erasure-request');
        $this->assertJsonPath('erasureRequested', null);

        $this->jsonRequest('POST', '/api/me/erasure-request', ['note' => 'Please delete everything.']);
        self::assertResponseStatusCodeSame(201);
        $this->assertJsonPath('receivedAt', '2026-11-08T10:00:00+01:00');
        $this->assertJsonPath('dueOn', '2026-12-08');

        $this->jsonRequest('POST', '/api/me/erasure-request', []);
        $this->assertProblem(409, 'privacy.alreadyRequested');

        $this->jsonRequest('GET', '/api/me/erasure-request');

        $this->assertJsonPath('erasureRequested', [
            'receivedAt' => '2026-11-08T10:00:00+01:00',
            'dueOn' => '2026-12-08',
        ]);

        $this->signInWithRoles('admin@example.org', 'administrator');
        $this->jsonRequest('GET', '/api/data-requests');
        $this->assertJsonPath('open.0.channel', 'app');
        $this->assertJsonPath('open.0.kind', 'erasure');
        $this->assertJsonPath('open.0.note', 'Please delete everything.');
    }

    public function testALongNoteOrAForeignOriginIsRefused(): void
    {
        $this->signInWithRoles('hoda@example.org', 'parent');

        $this->jsonRequest('POST', '/api/me/erasure-request', ['note' => str_repeat('a', times: 2001)]);
        $this->assertFieldError('note', 'validation.tooLong', ['max' => 2000, 'limit' => 2000]);

        $this->jsonRequest('POST', '/api/me/erasure-request', [], ['Origin' => 'https://evil.example']);
        $this->assertProblem(403, 'forbidden');

        $this->jsonRequest('GET', '/api/me/erasure-request');
        $this->assertJsonPath('erasureRequested', null);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function exportOf(string $accountId): array
    {
        $export = self::getContainer()->get(ExportAccountDataWorkflow::class);
        self::assertInstanceOf(ExportAccountDataWorkflow::class, $export);

        $serializer = self::getContainer()->get(SerializerInterface::class);
        self::assertInstanceOf(SerializerInterface::class, $serializer);

        $encoded = $serializer->serialize(AccountDataResponse::of($export($accountId), memberRecord: null), 'json');

        return JsonBody::asArray(json_decode($encoded, associative: true, flags: JSON_THROW_ON_ERROR), 'export');
    }
}
