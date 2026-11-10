<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Lifecycle;

use App\Context\Meetings\Domain\Entity\Session;
use App\Library\Time\Client\Calendar;
use App\Library\Types\Model\Type;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

final readonly class SessionGuards
{
    private const string OPENS_ON_THE_DAY = 'meetings.opensOnTheDay';

    public function __construct(
        private Calendar $calendar,
    ) {
    }

    #[AsGuardListener(workflow: 'session_lifecycle', transition: 'open')]
    public function blockBeforeItsDay(GuardEvent $event): void
    {
        $session = Type::of($event->getSubject())->toInstanceOf(Session::class);

        if ($this->calendar->today()->isBefore($session->occurrenceDate())) {
            $event->addTransitionBlocker(new TransitionBlocker(self::OPENS_ON_THE_DAY, self::OPENS_ON_THE_DAY));
        }
    }
}
