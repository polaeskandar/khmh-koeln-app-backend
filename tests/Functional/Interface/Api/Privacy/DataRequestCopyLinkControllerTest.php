<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Privacy;

use App\Context\Privacy\Domain\Enum\RequestKind;
use App\Library\Types\Model\Integers;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\MemberLinks;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Privacy\CopyLinks;
use App\Tests\Support\Interface\Api\Privacy\ErasureFixture;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mime\Email;

final class DataRequestCopyLinkControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;
    use MailerAssertionsTrait;

    private const string URL = '#^https://app\.khmk-koeln\.com/%s/data-copy/\#token=[A-Za-z0-9_-]{43}$#';

    public function testAnAccountGetsTheLinkByEmailInItsLanguage(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $account = new Families(self::getContainer())->account('hoda@example.org', ['language' => 'ar']);
        $id = new ErasureFixture(self::getContainer())->request(RequestKind::Copy, null, $account);
        $this->signInWithRoles('admin@example.org', 'administrator');

        $url = $this->issued($id);

        self::assertMatchesRegularExpression(sprintf(self::URL, 'ar'), $url);
        $this->assertJsonPath('expiresAt', '2026-11-15T10:00:00+01:00');
        $this->assertJsonPath('emailedTo', 'hoda@example.org');

        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('نسختك من بياناتك لدى KHMK كولونيا', $email->getSubject());
        self::assertSame('hoda@example.org', ($email->getTo()[0] ?? null)?->getAddress());
        self::assertStringContainsString($url, (string) $email->getTextBody());
        self::assertStringContainsString('15.11.2026 10:00', (string) $email->getTextBody());

        $this->jsonRequest('GET', '/api/data-requests/' . $id);

        $this->assertJsonPath('copyLink', [
            'expiresAt' => '2026-11-15T10:00:00+01:00',
            'emailedTo' => 'hoda@example.org',
            'usedAt' => null,
        ]);

        $token = CopyLinks::tokenOf($url);
        self::assertSame(0, $this->numberOf('SELECT count(*) FROM privacy.copy_link WHERE token_hash = ?', [$token]));

        self::assertSame(1, $this->numberOf('SELECT count(*) FROM privacy.copy_link WHERE token_hash = ?', [hash(
            'sha256',
            $token,
        )]));
    }

    public function testAMemberRecordWithAnAccountGetsTheLinkAtThatAddress(): void
    {
        $mina = new Rosters(self::getContainer())->person('Mina', 'Hanna');
        $account = new Families(self::getContainer())->account('mina@example.org', ['language' => 'en']);
        new MemberLinks(self::getContainer())->link($account, $mina->id()->toRfc4122());
        $id = new ErasureFixture(self::getContainer())->request(RequestKind::Copy, $mina);
        $this->signInWithRoles('admin@example.org', 'administrator');

        self::assertMatchesRegularExpression(sprintf(self::URL, 'en'), $this->issued($id));
        $this->assertJsonPath('emailedTo', 'mina@example.org');
        self::assertEmailCount(1);
    }

    public function testAMemberWithoutAnAccountGetsAGermanLinkTheOfficeHandsOver(): void
    {
        $mina = new Rosters(self::getContainer())->person('Mina', 'Hanna');
        $id = new ErasureFixture(self::getContainer())->request(RequestKind::Copy, $mina);
        $this->signInWithRoles('admin@example.org', 'administrator');

        self::assertMatchesRegularExpression(sprintf(self::URL, 'de'), $this->issued($id));
        $this->assertJsonPath('emailedTo', null);
        self::assertEmailCount(0);

        $this->jsonRequest('GET', '/api/data-requests/' . $id);
        $this->assertJsonPath('copyLink.emailedTo', null);
    }

    public function testANewLinkVoidsTheOldOne(): void
    {
        $mina = new Rosters(self::getContainer())->person('Mina', 'Hanna');
        $id = new ErasureFixture(self::getContainer())->request(RequestKind::Copy, $mina);
        $this->signInWithRoles('admin@example.org', 'administrator');

        $older = CopyLinks::tokenOf($this->issued($id));
        $newer = CopyLinks::tokenOf($this->issued($id));

        $this->jsonRequest('POST', '/api/data-copies', ['token' => $older]);
        $this->assertProblem(422, 'privacy.copyLinkInvalid');
        $this->jsonRequest('POST', '/api/data-copies', ['token' => $newer]);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->numberOf('SELECT count(*) FROM privacy.copy_link'));
    }

    public function testOnlyAnOpenCopyRequestWithItsSubjectGetsALink(): void
    {
        $fixture = new ErasureFixture(self::getContainer());
        $mina = new Rosters(self::getContainer())->person('Mina', 'Hanna');
        $erasure = $fixture->request(RequestKind::Erasure, $mina);
        $closed = $fixture->request(RequestKind::Copy, $mina);
        $gone = $fixture->request(RequestKind::Copy, $mina);
        $connection = new Database(self::getContainer())->connection();

        $connection->update(
            'privacy.data_request',
            ['status' => 'done', 'closed_at' => '2026-11-03 12:00:00+01'],
            ['id' => $closed],
        );

        $connection->update(
            'privacy.data_request',
            ['person_id' => null, 'erased_subject_kind' => 'member'],
            ['id' => $gone],
        );

        $this->signInWithRoles('daniel@example.org', 'servant');
        $this->jsonRequest('POST', '/api/data-requests/' . $closed . '/copy-link');
        $this->assertProblem(403, 'privacy.adminOnly');

        $this->signInWithRoles('admin@example.org', 'administrator');

        foreach ([
            [$erasure,        409, 'privacy.notCopy'],
            [$closed,         409, 'privacy.notOpen'],
            [$gone,           409, 'privacy.subjectGone'],
            ['not-a-request', 404, 'notFound.request'],
        ] as [$request, $status, $code]) {
            $this->jsonRequest('POST', '/api/data-requests/' . $request . '/copy-link');
            $this->assertProblem($status, $code);
        }

        self::assertSame(0, $this->numberOf('SELECT count(*) FROM privacy.copy_link'));
    }

    public function testErasingTheAccountForgetsWhereTheLinkWent(): void
    {
        $account = new Families(self::getContainer())->account('hoda@example.org');
        $id = new ErasureFixture(self::getContainer())->request(RequestKind::Copy, null, $account);
        $this->signInWithRoles('admin@example.org', 'administrator');
        $this->issued($id);

        $tester = new CommandTester(new Application($this->browser()->getKernel())->find('app:privacy:erase-account'));
        $tester->execute(['account' => $account]);
        $tester->assertCommandIsSuccessful();

        self::assertSame(0, $this->numberOf('SELECT count(*) FROM privacy.copy_link WHERE recipient_id IS NOT NULL'));
        $this->jsonRequest('GET', '/api/data-requests/' . $id);
        $this->assertJsonPath('copyLink.emailedTo', null);
        $this->assertJsonPath('request.erasedSubject', ['kind' => 'account', 'group' => null]);
    }

    private function issued(string $id): string
    {
        $this->jsonRequest('POST', '/api/data-requests/' . $id . '/copy-link');
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['url', 'expiresAt', 'emailedTo'], array_keys($this->responseJson()));

        return Type::of($this->responseJson()['url'] ?? null)->toString();
    }

    /**
     * @param list<string> $params
     */
    private function numberOf(string $sql, array $params = []): int
    {
        return Integers::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne($sql, $params),
        );
    }
}
