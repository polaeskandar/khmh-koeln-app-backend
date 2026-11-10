<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Query;

use App\Context\Identity\Domain\Entity\User;
use Doctrine\Common\Collections\ReadableCollection;

interface UserQueryInterface
{
    /**
     * @param positive-int $limit
     *
     * @return ReadableCollection<int, User>
     */
    public function listPending(int $limit): ReadableCollection;
}
