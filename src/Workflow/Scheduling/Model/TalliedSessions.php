<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\Scheduling\Domain\Entity\Session;
use Doctrine\Common\Collections\ReadableCollection;
use LogicException;

final readonly class TalliedSessions
{
    /**
     * @param ReadableCollection<int, Session> $sessions
     * @param ReadableCollection<non-empty-string, SessionTally> $tallies
     */
    public function __construct(
        public ReadableCollection $sessions,
        private ReadableCollection $tallies,
    ) {
    }

    public function tallyOf(Session $session): SessionTally
    {
        $tally = $this->tallies->get($session->id()->toRfc4122());

        return $tally ?? throw new LogicException('Every session has a tally.');
    }

    /**
     * @return list<SessionTally>
     */
    public function inOrder(): array
    {
        return $this->sessions->map($this->tallyOf(...))->getValues();
    }
}
