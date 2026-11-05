<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Entity\SessionEvent;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class MeetingSessions
{
    public function __construct(
        private SessionRepositoryInterface $sessions,
    ) {
    }

    public function find(string $id): Session
    {
        return $this->sessions->find(Uuid::fromString($id)) ?? throw NotFound::of('session');
    }

    /**
     * @return ReadableCollection<int, SessionEvent>
     */
    public function historyOf(Session $session): ReadableCollection
    {
        return $this->sessions->historyOf($session);
    }
}
