<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\People\Model\PersonReference;

final readonly class AudienceRow
{
    /**
     * @param list<AccountReference> $parentAccounts
     */
    public function __construct(
        public PersonReference $person,
        public bool $inStage,
        public ?ReportView $report,
        public array $parentAccounts,
        public bool $ownAccount,
    ) {
    }
}
