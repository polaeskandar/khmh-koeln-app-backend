<?php

declare(strict_types=1);

namespace App\Interface\Console\Identity;

use App\Library\Problem\Exception\NotFound;
use App\Workflow\Identity\SendTestMailWorkflow;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:mail:test', description: 'Send a test email to an account, through the configured MAILER_DSN')]
final readonly class MailTestCommand
{
    public function __construct(
        private SendTestMailWorkflow $sendTestMail,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email address of the account')]
        string $email,
    ): int {
        try {
            ($this->sendTestMail)($email);
        } catch (NotFound $missing) {
            $io->error($missing->getMessage());

            return Command::FAILURE;
        }

        $io->success('The test email is sent when this command ends.');

        return Command::SUCCESS;
    }
}
