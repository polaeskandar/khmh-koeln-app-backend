<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Privacy;

use App\Context\People\Domain\Entity\Person;
use App\Context\Privacy\Domain\Enum\RequestKind;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Interface\Api\Privacy\CopyLinks;
use App\Tests\Support\Interface\Api\Privacy\ErasureFixture;
use App\Tests\Support\JsonBody;
use SensitiveParameter;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;

final class DataCopiesControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string INVALID = '{"status":422,"code":"privacy.copyLinkInvalid","params":{}}';

    public function testTheLinkDownloadsTheAccountsCopyOnceAndClosesTheRequest(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $account = new Families(self::getContainer())->account('hoda@example.org', ['display_name' => 'Hoda Farag']);
        $id = new ErasureFixture(self::getContainer())->request(RequestKind::Copy, null, $account);
        $token = $this->linked($id);
        $this->browser()->getCookieJar()->clear();

        self::mockTime('2026-11-09T18:00:00+01:00');
        $this->download($token);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=khmk-my-data.json');
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame($this->exported(['--account' => $account]), $this->responseJson());
        $this->assertJsonPath('account.name', 'Hoda Farag');

        $this->download($token);
        self::assertSame(self::INVALID, $this->browser()->getResponse()->getContent());

        $this->signInAs('admin@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/data-requests/' . $id);
        $this->assertJsonPath('request.status', 'done');
        $this->assertJsonPath('request.closedAt', '2026-11-09T18:00:00+01:00');
        $this->assertJsonPath('request.closedBy', null);
        $this->assertJsonPath('request.outcome', null);
        $this->assertJsonPath('copyLink.usedAt', '2026-11-09T18:00:00+01:00');
    }

    public function testAMembersLinkDownloadsTheMembersCopy(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $mina = new Rosters(self::getContainer())->person('Mina', 'Hanna');
        $token = $this->linked(new ErasureFixture(self::getContainer())->request(RequestKind::Copy, $mina));
        $this->browser()->getCookieJar()->clear();

        $this->download($token);

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('kind', 'member');
        self::assertSame($this->exported(['--person' => $this->idOf($mina)]), $this->responseJson());
    }

    public function testALinkExpiresAfterSevenDays(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $mina = new Rosters(self::getContainer())->person('Mina', 'Hanna');
        $id = new ErasureFixture(self::getContainer())->request(RequestKind::Copy, $mina);
        $token = $this->linked($id);

        self::mockTime('2026-11-15T10:00:01+01:00');
        $this->download($token);
        self::assertSame(self::INVALID, $this->browser()->getResponse()->getContent());

        $this->jsonRequest('GET', '/api/data-requests/' . $id);
        $this->assertJsonPath('request.status', 'open');
        $this->assertJsonPath('copyLink.usedAt', null);
    }

    public function testAMissingOrUnknownTokenGetsTheSameAnswer(): void
    {
        $this->jsonRequest('POST', '/api/data-copies');
        self::assertSame(self::INVALID, $this->browser()->getResponse()->getContent());

        foreach (['', 'an unknown token', str_repeat('a', times: 43)] as $token) {
            $this->download($token);
            self::assertResponseStatusCodeSame(422);
            self::assertSame(self::INVALID, $this->browser()->getResponse()->getContent());
        }
    }

    public function testAnEleventhDownloadWithinAnHourIsThrottled(): void
    {
        $this->browser()->disableReboot();

        foreach (range(start: 1, end: 10) as $attempt) {
            $this->download('an unknown token ' . $attempt);
            self::assertResponseStatusCodeSame(422);
        }

        $this->download('an unknown token');
        $this->assertProblem(429, 'auth.tooManyAttempts');
        self::assertTrue($this->browser()->getResponse()->headers->has('Retry-After'));
    }

    private function linked(string $requestId): string
    {
        $this->signInWithRoles('admin@example.org', 'administrator');
        $this->jsonRequest('POST', '/api/data-requests/' . $requestId . '/copy-link');
        self::assertResponseStatusCodeSame(201);

        return CopyLinks::tokenOf(Type::of($this->responseJson()['url'] ?? null)->toString());
    }

    private function download(#[SensitiveParameter] string $token): void
    {
        $this->jsonRequest('POST', '/api/data-copies', ['token' => $token]);
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

    private function idOf(Person $person): string
    {
        return $person->id()->toRfc4122();
    }
}
