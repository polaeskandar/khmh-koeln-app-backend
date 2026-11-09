<?php

declare(strict_types=1);

namespace App\Interface\Console\Identity;

use App\Interface\Console\Identity\Request\UserCreateRequest;
use App\Library\Console\Client\SecretReader;
use App\Library\Console\Exception\ConfirmationMismatch;
use App\Library\Console\Exception\HiddenInputUnavailable;
use App\Library\Console\Exception\TerminalRequired;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\Text;
use App\Workflow\Identity\CreateAccountWorkflow;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:create', description: 'Create an approved account')]
final readonly class UserCreateCommand
{
    public function __construct(
        private CreateAccountWorkflow $createAccount,
        private SecretReader $secrets,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        InputInterface $input,
        #[Argument(description: 'Email address, compared in lower case')]
        string $email,
        #[Argument(description: 'Display name')]
        string $name,
        #[MapInput]
        UserCreateRequest $options,
    ): int {
        try {
            $account = ($this->createAccount)(
                email: $email,
                displayName: $name,
                language: $options->language,
                password: $this->password($io, $input, $options),
                role: $options->role,
            );
        } catch (InvalidInput $invalid) {
            self::report($io, $invalid);

            return Command::INVALID;
        } catch (ConfirmationMismatch|HiddenInputUnavailable|TerminalRequired $unusable) {
            $io->error($unusable->getMessage());

            return Command::FAILURE;
        }

        $io->success(Text::format('Created the approved account %s.', $account)->toString());

        return Command::SUCCESS;
    }

    private function password(SymfonyStyle $io, InputInterface $input, UserCreateRequest $options): ?string
    {
        return match (true) {
            $options->noPassword => null,
            $options->passwordStdin => $this->secrets->fromStandardInput($input),
            default => $this->secrets->typed($io, $input, prompt: 'Password'),
        };
    }

    private static function report(SymfonyStyle $io, InvalidInput $invalid): void
    {
        foreach ($invalid->fieldErrors as $field => $error) {
            $io->error(Text::format('%s: %s', $field, $error['code'])->toString());
        }
    }
}
