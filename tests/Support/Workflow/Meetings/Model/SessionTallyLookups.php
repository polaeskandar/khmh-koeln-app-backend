<?php

declare(strict_types=1);

namespace App\Tests\Support\Workflow\Meetings\Model;

use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Query\AttendanceQueryInterface;
use App\Context\People\Application\Service\Attendees;
use App\Workflow\Meetings\Model\SessionTallies;
use App\Workflow\Meetings\Model\SessionTally;
use Doctrine\Common\Collections\ReadableCollection;
use PHPUnit\Framework\Assert;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class SessionTallyLookups
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return ReadableCollection<non-empty-string, SessionTally>
     */
    public function tallies(ReadableCollection $sessions): ReadableCollection
    {
        $schedules = $this->service(MeetingLookup::class)->schedules(SessionTallies::scheduleIdsOf($sessions));

        return SessionTallies::of(
            $this->service(Attendees::class)->rostersOn(SessionTallies::rosterKeysOf($sessions, $schedules)),
            $this->service(AttendanceQueryInterface::class)->listPresentBySession(SessionTallies::sessionIdsOf(
                $sessions,
            )),
            $sessions,
            $schedules,
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $class): object
    {
        $service = $this->container->get($class);
        Assert::assertInstanceOf($class, $service);

        return $service;
    }
}
