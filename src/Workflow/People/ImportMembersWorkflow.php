<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\People\Application\Service\MemberImports;
use App\Library\Files\Client\CsvFiles;
use App\Workflow\People\Model\ImportReport;

final readonly class ImportMembersWorkflow
{
    public function __construct(
        private ReadMemberImportWorkflow $readMemberImport,
        private MemberImports $imports,
        private CsvFiles $files,
    ) {
    }

    public function __invoke(string $path, string $duplicates): ImportReport
    {
        $plan = ($this->readMemberImport)($path, $duplicates);

        if ($plan->isValid()) {
            $this->imports->save($plan);
            $this->files->delete($path);
        }

        return ImportReport::of($plan, done: 'Imported');
    }
}
