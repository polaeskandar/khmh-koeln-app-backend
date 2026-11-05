<?php

declare(strict_types=1);

namespace App\Interface\Api\Attendance\Response;

use App\Workflow\Attendance\Model\AttendanceOverview;
use App\Workflow\Attendance\Model\OverviewCard;
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
     *     sessions: list<array<string, scalar|object|array<string, bool>|null>>,
     *     earlier: list<array<string, scalar|object|array<string, bool>|null>>,
     *     closed: list<array<string, scalar|object|array<string, bool>|null>>,
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
     * @return list<array<string, scalar|object|array<string, bool>|null>>
     */
    private static function cards(array $cards): array
    {
        return new ArrayCollection($cards)->map(static fn (OverviewCard $card): array => [
            ...new BoardSessionResponse($card->session)->jsonSerialize(),
            'counts' => $card->counts,
        ])->getValues();
    }
}
