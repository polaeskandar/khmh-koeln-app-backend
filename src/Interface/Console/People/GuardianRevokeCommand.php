<?php

declare(strict_types=1);

namespace App\Interface\Console\People;

use App\Library\Problem\Exception\NotFound;
use App\Library\Strings\Model\Text;
use App\Workflow\People\ListGuardianLinksWorkflow;
use App\Workflow\People\Model\GuardianLink;
use App\Workflow\People\RevokeGuardianWorkflow;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:guardian:revoke', description: 'List a parent account\'s children, or revoke access to one')]
final readonly class GuardianRevokeCommand
{
    public function __construct(
        private ListGuardianLinksWorkflow $listLinks,
        private RevokeGuardianWorkflow $revokeGuardian,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Username of the parent account')]
        string $username,
        #[Argument(description: 'Id of the child whose access ends', name: 'personId')]
        ?string $personId = null,
    ): int {
        try {
            $this->showOrRevoke($io, $username, $personId);
        } catch (NotFound $unknown) {
            $io->error($unknown->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function showOrRevoke(SymfonyStyle $io, string $username, ?string $personId): void
    {
        if ($personId === null) {
            $this->show($io, $username);

            return;
        }

        ($this->revokeGuardian)($username, $personId);
        $io->success(Text::format('%s no longer acts for the child.', $username)->toString());
    }

    private function show(SymfonyStyle $io, string $username): void
    {
        $rows = new ArrayCollection(($this->listLinks)($username))->map(static fn (GuardianLink $link): array => [
            $link->name,
            $link->personId,
            $link->relationship,
        ]);

        $io->table(['Child', 'Id', 'Relationship'], $rows->getValues());
    }
}
