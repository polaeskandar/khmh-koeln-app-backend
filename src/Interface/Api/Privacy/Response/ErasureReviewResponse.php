<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Response;

use App\Workflow\Privacy\Model\ErasureReview;
use App\Workflow\Privacy\Model\Mention;

final readonly class ErasureReviewResponse
{
    /**
     * @param array<string, int> $erase
     * @param array<string, int> $keep
     * @param list<Mention> $mentions
     */
    public function __construct(
        public array $erase,
        public array $keep,
        public array $mentions,
    ) {
    }

    public static function of(ErasureReview $review): self
    {
        return new self($review->erase, $review->keep, $review->mentions);
    }
}
