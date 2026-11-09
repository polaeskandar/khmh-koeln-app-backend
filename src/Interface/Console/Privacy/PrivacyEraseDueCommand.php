<?php

declare(strict_types=1);

namespace App\Interface\Console\Privacy;

use App\Interface\Console\Privacy\Request\PrivacyEraseDueRequest;
use App\Library\Strings\Model\Text;
use App\Platform\Doctrine\Contract\Transactions;
use App\Workflow\Privacy\EraseDueRequestsWorkflow;
use App\Workflow\Privacy\Model\DueErasures;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:privacy:erase-due',
    description: 'Erase the accounts whose erasure from the app is due after its grace period (run daily)',
)]
final readonly class PrivacyEraseDueCommand
{
    public function __construct(
        private EraseDueRequestsWorkflow $eraseDueRequests,
        private Transactions $transactions,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[MapInput] PrivacyEraseDueRequest $options): int
    {
        $run = $options->dryRun
            ? $this->transactions->rehearse(fn (): DueErasures => ($this->eraseDueRequests)())
            : ($this->eraseDueRequests)();

        $io->success(
            Text::format(
                '%sErased %d scheduled requests, %d failed.',
                $options->dryRun ? 'Dry run, nothing changed: ' : '',
                $run->erased,
                $run->failed,
            )->toString(),
        );

        return $run->failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
