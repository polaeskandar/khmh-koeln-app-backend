<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Privacy;

use App\Context\Privacy\Domain\Enum\RequestKind;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Interface\Api\Privacy\ErasureFixture;
use App\Tests\Support\JsonBody;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;

final class DataRequestCopyControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testTheOfficeDownloadsAnAccountsCopyAsAFileAndTheRequestStaysOpen(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $account = new Families(self::getContainer())->account('hoda@example.org', ['display_name' => 'Hoda Farag']);
        $id = new ErasureFixture(self::getContainer())->request(RequestKind::Copy, null, $account);
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('GET', '/api/data-requests/' . $id . '/copy');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=khmk-data-copy.json');
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame($this->exported(['--account' => $account]), $this->responseJson());
        $this->assertJsonPath('account.name', 'Hoda Farag');

        $this->jsonRequest('GET', '/api/data-requests/' . $id);
        $this->assertJsonPath('request.status', 'open');
    }

    public function testAMembersCopyIsTheMembersExport(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $mina = new Rosters(self::getContainer())->person('Mina', 'Hanna');
        $id = new ErasureFixture(self::getContainer())->request(RequestKind::Copy, $mina);
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('GET', '/api/data-requests/' . $id . '/copy');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('kind', 'member');
        self::assertSame($this->exported(['--person' => $mina->id()->toRfc4122()]), $this->responseJson());
    }

    public function testOnlyAnOpenCopyRequestOfAnAdministratorGivesACopy(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $fixture = new ErasureFixture(self::getContainer());
        $mina = new Rosters(self::getContainer())->person('Mina', 'Hanna');
        $copy = $fixture->request(RequestKind::Copy, $mina);
        $erasure = $fixture->request(RequestKind::Erasure, $mina);

        $this->signInWithRoles('servant@example.org', 'servant');
        $this->jsonRequest('GET', '/api/data-requests/' . $copy . '/copy');
        $this->assertProblem(403, 'privacy.adminOnly');
        $this->jsonRequest('POST', '/api/data-requests/' . $copy . '/complete', []);
        $this->assertProblem(403, 'privacy.adminOnly');

        $this->signInWithRoles('admin@example.org', 'administrator');
        $this->jsonRequest('GET', '/api/data-requests/' . $erasure . '/copy');
        $this->assertProblem(409, 'privacy.notCopy');
        $this->jsonRequest('GET', '/api/data-requests/not-a-request/copy');
        $this->assertProblem(404, 'notFound.request');
    }

    public function testTheEmailedLinksAreGone(): void
    {
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', '/api/data-copies', []);
        self::assertResponseStatusCodeSame(404);
        $this->jsonRequest('POST', '/api/data-requests/019a3f00-0000-7000-8000-000000000001/copy-link', []);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @param array<string, string> $subject
     *
     * @return array<array-key, mixed>
     */
    private function exported(array $subject): array
    {
        $path = sys_get_temp_dir() . '/' . uniqid('copy-', more_entropy: true) . '.json';
        $tester = new CommandTester(new Application($this->browser()->getKernel())->find('app:privacy:export'));
        $tester->execute([...$subject, '--output' => $path]);
        $tester->assertCommandIsSuccessful();
        $exported = (string) file_get_contents($path);
        unlink($path);

        return JsonBody::asArray(json_decode($exported, associative: true, flags: JSON_THROW_ON_ERROR), 'export');
    }
}
