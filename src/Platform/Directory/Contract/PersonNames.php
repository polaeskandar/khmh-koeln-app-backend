<?php

declare(strict_types=1);

namespace App\Platform\Directory\Contract;

use Symfony\Component\Uid\Uuid;

interface PersonNames
{
    /**
     * @param list<Uuid> $personIds
     *
     * @return array<string, string> the name each person goes by, keyed by person id; erased people are left out
     */
    public function namesOf(array $personIds): array;
}
