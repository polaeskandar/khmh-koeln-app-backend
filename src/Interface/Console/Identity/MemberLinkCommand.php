<?php

declare(strict_types=1);

namespace App\Interface\Console\Identity;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Strings\Model\Text;
use App\Workflow\Identity\LinkMemberWorkflow;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:member:link', description: 'Link an approved account to its own member record')]
final readonly class MemberLinkCommand
{
    private const array AGES = [
        '16-or-older' => 'sixteenOrOlder',
        'guardian-consent' => 'guardianConsent',
    ];

    public function __construct(
        private LinkMemberWorkflow $linkMember,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email address of the account')]
        string $email,
        #[Argument(description: 'Id of the member record', name: 'personId')]
        string $personId,
        #[Option(description: 'How the age was checked: 16-or-older or guardian-consent')]
        string $age = '',
        #[Option(description: 'How a parent or guardian agreed, for guardian-consent')]
        ?string $note = null,
    ): int {
        $ageCheck = self::AGES[$age] ?? null;

        if ($ageCheck === null) {
            $io->error('The option --age takes one of: 16-or-older, guardian-consent.');

            return Command::INVALID;
        }

        try {
            ($this->linkMember)($email, $personId, $ageCheck, $note);
        } catch (NotFound|RuleViolation $refused) {
            $io->error($refused->getMessage());

            return Command::FAILURE;
        } catch (InvalidInput $invalid) {
            return self::refused($io, $invalid);
        }

        $io->success(Text::format('%s is linked to the member record %s.', $email, $personId)->toString());

        return Command::SUCCESS;
    }

    private static function refused(SymfonyStyle $io, InvalidInput $invalid): int
    {
        foreach ($invalid->fieldErrors as $field => $error) {
            $io->error(Text::format('%s: %s', $field, $error['code'])->toString());
        }

        return Command::FAILURE;
    }
}
