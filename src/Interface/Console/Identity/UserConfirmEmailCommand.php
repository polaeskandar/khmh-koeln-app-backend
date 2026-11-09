<?php

declare(strict_types=1);

namespace App\Interface\Console\Identity;

use App\Library\Problem\Exception\NotFound;
use App\Library\Strings\Model\Text;
use App\Workflow\Identity\ConfirmEmailByHandWorkflow;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:confirm-email',
    description: 'Confirm the email address of a registration, as if its link was used; sends no email',
)]
final readonly class UserConfirmEmailCommand
{
    private const string CONFIRMED = 'Confirmed %s. The registration now waits under Account requests.';

    private const string ALREADY_CONFIRMED = '%s is already confirmed. Nothing changed.';

    public function __construct(
        private ConfirmEmailByHandWorkflow $confirmEmail,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email address of the account')]
        string $email,
    ): int {
        try {
            $confirmed = ($this->confirmEmail)($email);
        } catch (NotFound $unknown) {
            $io->error(
                Text::format('%s: no account has the email address %s.', $unknown->getMessage(), $email)->toString(),
            );

            return Command::FAILURE;
        }

        $io->success(Text::format($confirmed ? self::CONFIRMED : self::ALREADY_CONFIRMED, $email)->toString());

        return Command::SUCCESS;
    }
}
