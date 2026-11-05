<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\Lesson;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class HomeworkTally
{
    /**
     * @param ReadableCollection<string, HomeworkCounts> $counts
     */
    public function __construct(
        private ReadableCollection $counts,
    ) {
    }

    public function of(Lesson $lesson): HomeworkCounts
    {
        return $this->counts->get($lesson->id()->toRfc4122()) ?? new HomeworkCounts();
    }
}
