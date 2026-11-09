<?php

declare(strict_types=1);

namespace App\Interface\Console\Identity;

use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Strings\Model\Text;
use App\Workflow\Identity\TakeRoleByEmailWorkflow;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:role:remove', description: 'Take a role from an account')]
final readonly class UserRoleRemoveCommand
{
    public function __construct(
        private TakeRoleByEmailWorkflow $takeRoleByEmail,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email address of the account')]
        string $email,
        #[Argument(description: 'Name of the role, for example "Servant"')]
        string $role,
    ): int {
        try {
            ($this->takeRoleByEmail)($email, $role);
        } catch (NotFound|RuleViolation $refused) {
            $io->error($refused->getMessage());

            return Command::FAILURE;
        }

        $io->success(Text::format('Took the role %s from %s.', $role, $email)->toString());

        return Command::SUCCESS;
    }
}
