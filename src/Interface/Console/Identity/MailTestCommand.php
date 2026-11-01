<?php

declare(strict_types=1);

namespace App\Interface\Console\Identity;

use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Interface\Application\Identity\Service\AccountMailer;
use App\Library\Types\Model\Type;
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
        private readonly UserRepositoryInterface $users,
        private readonly AccountMailer $mailer,
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
        $user = $this->users->findByEmail(Type::of($input->getArgument('email'))->toString());

        if ($user === null) {
            $io->error('notFound.account');

            return Command::FAILURE;
        }

        $this->mailer->send($user, 'test');
        $io->success('The test email is sent when this command ends.');

        return Command::SUCCESS;
    }
}
