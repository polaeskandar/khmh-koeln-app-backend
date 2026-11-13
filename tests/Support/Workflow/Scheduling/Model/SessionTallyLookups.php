<?php

declare(strict_types=1);

namespace App\Tests\Support\Workflow\Scheduling\Model;

use App\Context\People\Application\Service\Attendees;
use App\Context\Scheduling\Application\Service\MeetingLookup;
use App\Context\Scheduling\Domain\Entity\Session;
use App\Context\Scheduling\Domain\Query\AttendanceQueryInterface;
use App\Workflow\Scheduling\Model\SessionTallies;
use App\Workflow\Scheduling\Model\SessionTally;
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
        $schedules = $this->service(MeetingLookup::class)->listSchedules(SessionTallies::scheduleIdsOf($sessions));

        return SessionTallies::of(
            $this->service(Attendees::class)->listRostersOn(SessionTallies::rosterKeysOf($sessions, $schedules)),
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
