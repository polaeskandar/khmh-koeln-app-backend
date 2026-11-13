<?php

declare(strict_types=1);

namespace App\Interface\Console\Identity;

use App\Interface\Console\Identity\Request\UserCreateRequest;
use App\Library\Console\Client\SecretReader;
use App\Library\Console\Exception\ConfirmationMismatch;
use App\Library\Console\Exception\HiddenInputUnavailable;
use App\Library\Console\Exception\TerminalRequired;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Strings\Model\Text;
use App\Workflow\Identity\CreateUserWorkflow;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:create', description: 'Create an account')]
final readonly class UserCreateCommand
{
    public function __construct(
        private CreateUserWorkflow $createUser,
        private SecretReader $secrets,
    ) {
    }

    public function __invoke(SymfonyStyle $io, InputInterface $input, #[MapInput] UserCreateRequest $request): int
    {
        try {
            $account = ($this->createUser)(
                $request->details(),
                password: $this->password($io, $input, $request),
                role: $request->role,
            );
        } catch (InvalidInput $invalid) {
            self::report($io, $invalid);

            return Command::INVALID;
        } catch (ConfirmationMismatch|HiddenInputUnavailable|RuleViolation|TerminalRequired $unusable) {
            $io->error($unusable->getMessage());

            return Command::FAILURE;
        }

        $io->success(Text::format('Created the account %s.', $account)->toString());

        return Command::SUCCESS;
    }

    private function password(SymfonyStyle $io, InputInterface $input, UserCreateRequest $options): string
    {
        return $options->passwordStdin
            ? $this->secrets->fromStandardInput($input)
            : $this->secrets->typed($io, $input, prompt: 'Password');
    }

    private static function report(SymfonyStyle $io, InvalidInput $invalid): void
    {
        foreach ($invalid->fieldErrors as $field => $error) {
            $io->error(Text::format('%s: %s', $field, $error['code'])->toString());
        }
    }
}
