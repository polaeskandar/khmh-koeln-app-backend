<?php

declare(strict_types=1);

namespace App\Interface\Console\Identity;

use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Strings\Model\Text;
use App\Workflow\Identity\SendPasswordLinkWorkflow;
use DateTimeInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:password-link',
    description: 'Email a one-time link to set the password, valid for 24 hours',
)]
final readonly class UserPasswordLinkCommand
{
    public function __construct(
        private SendPasswordLinkWorkflow $sendPasswordLink,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email address of the account')]
        string $email,
    ): int {
        try {
            $validUntil = ($this->sendPasswordLink)($email);
        } catch (NotFound|RuleViolation $refused) {
            $io->error($refused->getMessage());

            return Command::FAILURE;
        }

        $io->success(
            Text::format(
                'The link to set the password is emailed to %s when this command ends. It is valid until %s.',
                $email,
                $validUntil->format(DateTimeInterface::ATOM),
            )->toString(),
        );

        return Command::SUCCESS;
    }
}
