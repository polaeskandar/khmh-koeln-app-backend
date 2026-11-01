<?php

declare(strict_types=1);

namespace App\Interface\Console\Identity;

use App\Interface\Application\Identity\UseCase\Command\GiveRole\GiveRoleCommand;
use App\Interface\Application\Identity\UseCase\Command\GiveRole\GiveRoleHandler;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Strings\Model\Text;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:role:add', description: 'Give an account a role')]
final readonly class UserRoleAddCommand
{
    public function __construct(
        private GiveRoleHandler $handler,
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
            ($this->handler)(new GiveRoleCommand($email, $role));
        } catch (NotFound|RuleViolation $refused) {
            $io->error($refused->getMessage());

            return Command::FAILURE;
        }

        $io->success(Text::format('Gave %s the role %s.', $email, $role)->toString());

        return Command::SUCCESS;
    }
}
