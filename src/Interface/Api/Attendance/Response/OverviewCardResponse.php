<?php

declare(strict_types=1);

namespace App\Interface\Api\Attendance\Response;

use App\Workflow\Attendance\Model\OverviewCard;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Override;

final readonly class OverviewCardResponse implements JsonSerializable
{
    public function __construct(
        private OverviewCard $card,
    ) {
    }

    /**
     * @param list<OverviewCard> $cards
     *
     * @return list<self>
     */
    public static function listOf(array $cards): array
    {
        return new ArrayCollection($cards)->map(static fn (OverviewCard $card): self => new self($card))->getValues();
    }

    /**
     * @return array<string, scalar|object|array<string, bool>|null>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [...new BoardSessionResponse($this->card->session)->jsonSerialize(), 'counts' => $this->card->counts];
    }
}
