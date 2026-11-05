<?php

declare(strict_types=1);

namespace App\Interface\Console\Identity;

use App\Library\Problem\Exception\NotFound;
use App\Library\Types\Model\Type;
use App\Workflow\Identity\SendTestMailWorkflow;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:mail:test', description: 'Send a test email to an account, through the configured MAILER_DSN')]
final class MailTestCommand extends Command
{
    public function __construct(
        private readonly SendTestMailWorkflow $sendTestMail,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email address of the account');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            ($this->sendTestMail)(Type::of($input->getArgument('email'))->toString());
        } catch (NotFound $missing) {
            $io->error($missing->getMessage());

            return Command::FAILURE;
        }

        $io->success('The test email is sent when this command ends.');

        return Command::SUCCESS;
    }
}
