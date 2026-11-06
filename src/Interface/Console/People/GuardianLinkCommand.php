<?php

declare(strict_types=1);

namespace App\Interface\Console\People;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Strings\Model\Text;
use App\Workflow\People\LinkGuardianWorkflow;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:guardian:link', description: 'Give a parent account access to a child')]
final readonly class GuardianLinkCommand
{
    public function __construct(
        private LinkGuardianWorkflow $linkGuardian,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email address of the parent account')]
        string $email,
        #[Argument(description: 'Id of the child', name: 'personId')]
        string $personId,
        #[Option(description: 'parent or guardian')]
        string $relationship = 'parent',
    ): int {
        try {
            ($this->linkGuardian)($email, $personId, $relationship);
        } catch (InvalidInput $invalid) {
            self::report($io, $invalid);

            return Command::INVALID;
        } catch (NotFound|RuleViolation $refused) {
            $io->error($refused->getMessage());

            return Command::FAILURE;
        }

        $io->success(Text::format('%s can act for the child.', $email)->toString());

        return Command::SUCCESS;
    }

    private static function report(SymfonyStyle $io, InvalidInput $invalid): void
    {
        foreach ($invalid->fieldErrors as $field => $error) {
            $io->error(Text::format('%s: %s', $field, $error['code'])->toString());
        }
    }
}
