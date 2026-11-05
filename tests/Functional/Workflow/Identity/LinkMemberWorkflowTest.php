<?php

declare(strict_types=1);

namespace App\Tests\Functional\Workflow\Identity;

use App\Tests\Support\CommittedRows;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use App\Workflow\Identity\LinkMemberWorkflow;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

#[SkipDatabaseRollback]
final class LinkMemberWorkflowTest extends KernelTestCase
{
    private const string EMAIL = 'locked.member@example.org';

    private ?Connection $other = null;

    private string $accountId = '';

    private string $personId = '';

    #[Override]
    protected function setUp(): void
    {
        $this->personId = new Rosters(self::getContainer())
            ->person('Locked', 'Member')
            ->id()
            ->toRfc4122();

        $this->accountId = Uuid::v7()->toRfc4122();

        new Database(self::getContainer())
            ->connection()
            ->insert('identity.app_user', Accounts::row(['id' => $this->accountId, 'email' => self::EMAIL]));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->other?->close();

        new CommittedRows(new Database(self::getContainer())->connection())->delete([
            'identity.app_user' => [$this->accountId],
            'people.person' => [$this->personId],
        ]);

        parent::tearDown();
    }

    public function testLinkingWaitsWhileThePersonIsBeingErasedAndWritesNothingOnTimeout(): void
    {
        $other = DriverManager::getConnection(new Database(self::getContainer())->connection()->getParams());
        $this->other = $other;
        $other->beginTransaction();
        $other->fetchOne('SELECT id FROM people.person WHERE id = ? FOR UPDATE', [$this->personId]);
        $connection = new Database(self::getContainer())->connection();
        $connection->executeStatement("SET lock_timeout = '200ms'");
        $sqlState = null;

        try {
            $this->link('sixteenOrOlder');
        } catch (DriverException $refused) {
            $sqlState = $refused->getSQLState();
        } finally {
            $other->rollBack();
            $connection->executeStatement('SET lock_timeout = 0');
        }

        self::assertSame('55P03', $sqlState);

        self::assertNull($connection->fetchOne('SELECT person_id FROM identity.app_user WHERE id = ?', [
            $this->accountId,
        ]));
    }

    public function testAnUnknownAgeCheckIsRefused(): void
    {
        self::assertSame(
            ['ageCheck' => ['code' => 'accounts.chooseAge']],
            ThrownFieldErrors::of(function (): void {
                $this->link('adult');
            }),
        );
    }

    private function link(string $ageCheck): void
    {
        $link = self::getContainer()->get(LinkMemberWorkflow::class);
        self::assertInstanceOf(LinkMemberWorkflow::class, $link);

        $link(self::EMAIL, $this->personId, $ageCheck, note: null);
    }
}
