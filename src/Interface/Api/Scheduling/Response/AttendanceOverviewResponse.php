<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling\Response;

use App\Workflow\Scheduling\Model\AttendanceOverview;
use App\Workflow\Scheduling\Model\OverviewCard;
use App\Workflow\Scheduling\Model\SessionState;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Override;

final readonly class AttendanceOverviewResponse implements JsonSerializable
{
    public function __construct(
        private AttendanceOverview $overview,
    ) {
    }

    /**
     * @return array{
     *     today: string,
     *     sessions: list<array{session: SessionResponse, state: SessionState}>,
     *     earlier: list<array{session: SessionResponse, state: SessionState}>,
     *     closed: list<array{session: SessionResponse, state: SessionState}>,
     *     openCount: int,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'today' => $this->overview->today,
            'sessions' => self::cards($this->overview->sessions),
            'earlier' => self::cards($this->overview->earlier),
            'closed' => self::cards($this->overview->closed),
            'openCount' => $this->overview->openCount,
        ];
    }

    /**
     * @param list<OverviewCard> $cards
     *
     * @return list<array{session: SessionResponse, state: SessionState}>
     */
    private static function cards(array $cards): array
    {
        return new ArrayCollection($cards)->map(static fn (OverviewCard $card): array => [
            'session' => new SessionResponse($card->session),
            'state' => $card->state,
        ])->getValues();
    }
}
