<?php

declare(strict_types=1);

namespace App\Interface\Console\Identity;

use App\Context\Identity\Domain\Enum\Language;
use App\Interface\Application\Identity\UseCase\Command\CreateUser\CreateUserCommand;
use App\Interface\Application\Identity\UseCase\Command\CreateUser\CreateUserHandler;
use App\Library\Console\Client\SecretReader;
use App\Library\Console\Exception\ConfirmationMismatch;
use App\Library\Console\Exception\HiddenInputUnavailable;
use App\Library\Console\Exception\InvalidOption;
use App\Library\Console\Exception\TerminalRequired;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\Text;
use App\Library\Types\Model\Type;
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
        private readonly CreateUserHandler $createUser,
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
            ->addOption(
                'no-password',
                mode: InputOption::VALUE_NONE,
                description: 'Set the password later through a password link',
            )
            ->addOption(
                'password-stdin',
                mode: InputOption::VALUE_NONE,
                description: 'Read the password from the first line of standard input',
            )
            ->addOption(
                'language',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Language: en, de or ar',
                default: 'de',
            );
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

            $account = ($this->createUser)(self::request($input, $password));
        } catch (InvalidInput $invalid) {
            self::report($io, $invalid);

            return Command::INVALID;
        } catch (ConfirmationMismatch|HiddenInputUnavailable|InvalidOption|TerminalRequired $unusable) {
            $io->error($unusable->getMessage());

            return Command::FAILURE;
        }

        $io->success(Text::format('Created the approved account %s.', $account->toRfc4122())->toString());

        return Command::SUCCESS;
    }

    private static function request(InputInterface $input, #[SensitiveParameter] ?string $password): CreateUserCommand
    {
        $language = Type::of($input->getOption('language'))->toString();

        return new CreateUserCommand(
            email: Type::of($input->getArgument('email'))->toString(),
            displayName: Type::of($input->getArgument('name'))->toString(),
            language: Language::tryFrom($language) ?? throw new InvalidOption('language', choices: 'en, de, ar'),
            password: $password,
        );
    }

    private static function report(SymfonyStyle $io, InvalidInput $invalid): void
    {
        foreach ($invalid->fieldErrors as $field => $error) {
            $io->error(Text::format('%s: %s', $field, $error['code'])->toString());
        }
    }
}
