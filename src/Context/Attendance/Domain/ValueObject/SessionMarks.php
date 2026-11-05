<?php

declare(strict_types=1);

namespace App\Context\Attendance\Domain\ValueObject;

use App\Context\Attendance\Domain\Entity\Attendance;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class SessionMarks
{
    /**
     * @param ReadableCollection<int, Attendance> $current
     * @param ReadableCollection<int, Attendance> $removed
     */
    public function __construct(
        public ReadableCollection $current,
        public ReadableCollection $removed,
    ) {
    }
}
