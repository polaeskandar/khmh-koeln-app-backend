<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ArchiveGuard;
use App\Context\Scheduling\Application\Service\CoveredSchedules;
use App\Context\Scheduling\Application\Service\OccurrenceCancellations;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Model\Ids;
use App\Library\Time\Exception\InvalidLocalDate;
use App\Library\Time\Model\LocalDate;
use App\Workflow\Scheduling\Model\CanceledOccurrence;
use Symfony\Component\Uid\Uuid;

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

        $day = self::dateOf($date);
        $planned = $this->cancellations->cancelable(Ids::toUuid($scheduleId, notFound: 'schedule'), $day);

        $this->access->requireIn(
            Action::Update,
            Resource::Sessions,
            CoveredSchedules::groupOf($planned->schedule),
            missing: 'schedule',
        );

        $this->archive->ensureOpen($planned->schedule->roster()->serviceId, $planned->schedule->roster()->stageId);

        return CanceledOccurrence::of($this->cancellations->cancel($planned, Uuid::fromString($accountId), $reason));
    }

    private static function dateOf(string $date): LocalDate
    {
        try {
            return LocalDate::of($date);
        } catch (InvalidLocalDate) {
            throw InvalidInput::field('date', 'validation.invalidDate');
        }
    }
}
