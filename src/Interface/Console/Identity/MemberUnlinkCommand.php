<?php

declare(strict_types=1);

namespace App\Interface\Console\Identity;

use App\Library\Problem\Exception\NotFound;
use App\Library\Strings\Model\Text;
use App\Workflow\Identity\UnlinkMemberByEmailWorkflow;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:member:unlink', description: 'Remove the link between an account and its member record')]
final readonly class MemberUnlinkCommand
{
    public function __construct(
        private UnlinkMemberByEmailWorkflow $unlinkMemberByEmail,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email address of the account')]
        string $email,
    ): int {
        try {
            ($this->unlinkMemberByEmail)($email);
        } catch (NotFound $refused) {
            $io->error($refused->getMessage());

            return Command::FAILURE;
        }

        $io->success(Text::format('%s is not linked to a member record.', $email)->toString());

        return Command::SUCCESS;
    }
}
