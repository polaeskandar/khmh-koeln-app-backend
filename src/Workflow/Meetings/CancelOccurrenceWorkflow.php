<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\OccurrenceCancellations;
use App\Context\Organization\Application\Service\ArchiveGuard;
use App\Workflow\Meetings\Model\CanceledOccurrence;

final readonly class CancelOccurrenceWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private OccurrenceCancellations $cancellations,
        private ArchiveGuard $archive,
    ) {
    }

    public function __invoke(string $scheduleId, string $date, string $accountId, ?string $reason): CanceledOccurrence
    {
        $this->access->require(Action::Update, Resource::Sessions);

        $planned = $this->cancellations->cancelable($scheduleId, $date);
        $this->archive->ensureOpen($planned->schedule->roster()->serviceId, $planned->schedule->roster()->stageId);

        return CanceledOccurrence::of($this->cancellations->cancel($planned, $accountId, $reason));
    }
}
