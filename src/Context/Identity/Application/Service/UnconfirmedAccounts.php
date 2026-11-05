<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Repository\UnconfirmedAccountRepositoryInterface;
use App\Library\Time\Client\Calendar;
use DateInterval;
use Symfony\Component\Uid\Uuid;

final readonly class UnconfirmedAccounts
{
    public function __construct(
        private UnconfirmedAccountRepositoryInterface $accounts,
        private Calendar $calendar,
    ) {
    }

    /**
     * @return list<Uuid>
     */
    public function purge(): array
    {
        $now = $this->calendar->now();
        $registeredBefore = $now->sub(new DateInterval(EmailConfirmations::LIFETIME));

        return $this->accounts->purgeExpired($registeredBefore->toDateTimeImmutable(), $now->toDateTimeImmutable());
    }
}
