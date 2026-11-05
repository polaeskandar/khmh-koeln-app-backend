<?php

declare(strict_types=1);

namespace App\Interface\Console\Privacy;

use App\Interface\Api\Privacy\Response\AccountDataResponse;
use App\Interface\Api\Privacy\Response\MemberDataResponse;
use App\Interface\Console\Privacy\Request\PrivacyExportRequest;
use App\Library\Files\Client\PrivateFiles;
use App\Library\Strings\Model\Text;
use App\Workflow\Privacy\ExportAccountDataWorkflow;
use App\Workflow\Privacy\ExportMemberDataWorkflow;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:privacy:export',
    description: "Export a member's or an account's personal data as JSON (Art. 15/20)",
)]
final readonly class PrivacyExportCommand
{
    public function __construct(
        private ExportMemberDataWorkflow $exportMemberData,
        private ExportAccountDataWorkflow $exportAccountData,
        private PrivateFiles $files,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'The member (person) id')]
        ?string $person = null,
        #[Option(description: 'The account id')]
        ?string $account = null,
        #[Option(description: 'The file to write, readable only by you')]
        ?string $output = null,
    ): int {
        $request = new PrivacyExportRequest($person, $account, $output);

        if (!$request->isValid()) {
            $io->error('Pass exactly one of --person=UUID or --account=UUID.');

            return Command::INVALID;
        }

        $data = $request->isMember()
            ? MemberDataResponse::of(($this->exportMemberData)($request->id()))
            : AccountDataResponse::of(($this->exportAccountData)($request->id()));

        if ($data === null) {
            $io->error(Text::format('notFound.%s', $request->isMember() ? 'person' : 'account')->toString());

            return Command::FAILURE;
        }

        $this->files->writeJson($request->path(), $data);
        $io->success(Text::format('Wrote %s (readable only by you).', $request->path())->toString());

        return Command::SUCCESS;
    }
}
