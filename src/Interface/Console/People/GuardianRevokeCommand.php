<?php

declare(strict_types=1);

namespace App\Interface\Console\People;

use App\Library\Problem\Exception\NotFound;
use App\Library\Strings\Model\Text;
use App\Workflow\People\ListGuardianLinksByEmailWorkflow;
use App\Workflow\People\Model\LinkedChild;
use App\Workflow\People\RevokeGuardianLinkByEmailWorkflow;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:guardian:revoke', description: 'List a parent account\'s children, or revoke access to one')]
final readonly class GuardianRevokeCommand
{
    public function __construct(
        private ListGuardianLinksByEmailWorkflow $listGuardianLinksByEmail,
        private RevokeGuardianLinkByEmailWorkflow $revokeGuardianLinkByEmail,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email address of the parent account')]
        string $email,
        #[Argument(description: 'Id of the child whose access ends', name: 'personId')]
        ?string $personId = null,
    ): int {
        try {
            $this->showOrRevoke($io, $email, $personId);
        } catch (NotFound $unknown) {
            $io->error($unknown->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function showOrRevoke(SymfonyStyle $io, string $email, ?string $personId): void
    {
        if ($personId === null) {
            $this->show($io, $email);

            return;
        }

        ($this->revokeGuardianLinkByEmail)($email, $personId);
        $io->success(Text::format('%s no longer acts for the child.', $email)->toString());
    }

    private function show(SymfonyStyle $io, string $email): void
    {
        $rows = new ArrayCollection(($this->listGuardianLinksByEmail)(
            $email,
        ))->map(static fn (LinkedChild $link): array => [
            $link->name,
            $link->personId,
            $link->relationship,
        ]);

        $io->table(['Child', 'Id', 'Relationship'], $rows->getValues());
    }
}
