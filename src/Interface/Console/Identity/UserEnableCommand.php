<?php

declare(strict_types=1);

namespace App\Interface\Console\Identity;

use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Strings\Model\Text;
use App\Workflow\Identity\EnableUserWorkflow;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:enable', description: 'Enable a disabled account again')]
final readonly class UserEnableCommand
{
    public function __construct(
        private EnableUserWorkflow $enableUser,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Username of the account')]
        string $username,
    ): int {
        try {
            ($this->enableUser)($username);
        } catch (NotFound|RuleViolation $refused) {
            $io->error($refused->getMessage());

            return Command::FAILURE;
        }

        $io->success(Text::format('Enabled %s.', $username)->toString());

        return Command::SUCCESS;
    }
}
