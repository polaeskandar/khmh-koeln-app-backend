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
            ['kind', 'exportedAt', 'account', 'children', 'homeworkReports', 'memberRecord'],
            array_keys($this->responseJson()),
        );

        self::assertSame($this->exportOf($parent), $this->responseJson());
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
