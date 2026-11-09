<?php

declare(strict_types=1);

namespace App\Context\Privacy\Application\Service;

use App\Context\Privacy\Domain\Repository\DataRequestRepositoryInterface;
use App\Context\Privacy\Domain\ValueObject\ErasureRun;
use App\Library\Time\Client\Calendar;
use App\Platform\Doctrine\Contract\Transactions;
use Doctrine\Common\Collections\ArrayCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class ErasureRuns
{
    public function __construct(
        private DataRequestRepositoryInterface $requests,
        private Calendar $calendar,
        private Transactions $transactions,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param callable(Uuid): bool $erase
     */
    public function eachDue(callable $erase): ErasureRun
    {
        return new ArrayCollection($this->requests->erasuresDueOn($this->calendar->today()))->reduce(fn (
            ErasureRun $run,
            Uuid $id,
        ): ErasureRun => $run->plus($this->safely($id, $erase)), initial: new ErasureRun());
    }

    /**
     * @param callable(Uuid): bool $erase
     */
    private function safely(Uuid $id, callable $erase): ErasureRun
    {
        try {
            return new ErasureRun(erased: (int) $erase($id));
        } catch (Throwable) {
            $this->transactions->recover();
            $this->logger->error('A scheduled erasure failed.', ['requestId' => $id->toRfc4122()]);

            return new ErasureRun(failed: 1);
        }
    }
}
