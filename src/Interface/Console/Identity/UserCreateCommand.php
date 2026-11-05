<?php

declare(strict_types=1);

namespace App\Interface\Console\Identity;

use App\Library\Console\Client\SecretReader;
use App\Library\Console\Exception\ConfirmationMismatch;
use App\Library\Console\Exception\HiddenInputUnavailable;
use App\Library\Console\Exception\TerminalRequired;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\Text;
use App\Library\Types\Model\Type;
use App\Workflow\Identity\CreateUserWorkflow;
use Override;
use SensitiveParameter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:create', description: 'Create an approved account')]
final class UserCreateCommand extends Command
{
    public function __construct(
        private readonly CreateUserWorkflow $createUser,
        private readonly SecretReader $secrets,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email address, compared in lower case')
            ->addArgument('name', InputArgument::REQUIRED, 'Display name')
            ->addOption('no-password', mode: InputOption::VALUE_NONE, description: 'Set the password later by link')
            ->addOption('password-stdin', mode: InputOption::VALUE_NONE, description: 'Read the password from stdin')
            ->addOption('role', mode: InputOption::VALUE_REQUIRED, description: 'Assign this role everywhere')
            ->addOption('language', mode: InputOption::VALUE_REQUIRED, description: 'en, de or ar', default: 'de');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $password = $this->secrets->read(
                $io,
                $input,
                noneOption: 'no-password',
                standardInputOption: 'password-stdin',
                prompt: 'Password',
            );

            $account = $this->create($input, $password);
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

    private function create(InputInterface $input, #[SensitiveParameter] ?string $password): string
    {
        return ($this->createUser)(
            email: Type::of($input->getArgument('email'))->toString(),
            displayName: Type::of($input->getArgument('name'))->toString(),
            language: Type::of($input->getOption('language'))->toString(),
            password: $password,
            role: Type::of($input->getOption('role'))->toStringOrNull(),
        );
    }

    private static function report(SymfonyStyle $io, InvalidInput $invalid): void
    {
        foreach ($invalid->fieldErrors as $field => $error) {
            $io->error(Text::format('%s: %s', $field, $error['code'])->toString());
        }
    }
}
