<?php

declare(strict_types=1);

namespace App\Interface\Console\Identity;

use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Strings\Model\Text;
use App\Workflow\Identity\TakeRoleWorkflow;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:role:remove', description: 'Take a role from an account')]
final readonly class UserRoleRemoveCommand
{
    public function __construct(
        private TakeRoleWorkflow $takeRole,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Username of the account')]
        string $username,
        #[Argument(description: 'Name of the role, for example "Servant"')]
        string $role,
    ): int {
        try {
            ($this->takeRole)($username, $role);
        } catch (NotFound|RuleViolation $refused) {
            $io->error($refused->getMessage());

            return Command::FAILURE;
        }

        $io->success(Text::format('Took the role %s from %s.', $role, $username)->toString());

        return Command::SUCCESS;
    }
}
