<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Meetings\Domain\Entity\Session;
use App\Library\Time\Client\Calendar;
use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\Meetings\Model\SessionView;

final readonly class BoardSession
{
    public function __construct(
        public SessionView $view,
        public ?string $openedAt,
        public ?string $closedAt,
        public ?AccountReference $closedBy,
    ) {
    }

    public static function of(Session $session, SessionView $view, AccountLookup $accounts, Calendar $calendar): self
    {
        $progress = $session->progress();
        $closedBy = $progress->closed->by;
        $closer = $closedBy === null ? null : $accounts->account($closedBy);

        return new self(
            $view,
            $progress->opened->at === null ? null : $calendar->formatInstant($progress->opened->at),
            $progress->closed->at === null ? null : $calendar->formatInstant($progress->closed->at),
            $closer === null ? null : AccountReference::of($closer),
        );
    }
}
