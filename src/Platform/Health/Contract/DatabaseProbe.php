<?php

declare(strict_types=1);

namespace App\Platform\Health\Contract;

interface DatabaseProbe
{
    /**
     * @param positive-int $timeoutSeconds
     */
    public function isAvailable(int $timeoutSeconds): bool;
}
