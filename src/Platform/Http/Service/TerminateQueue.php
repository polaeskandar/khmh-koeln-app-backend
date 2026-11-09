<?php

declare(strict_types=1);

namespace App\Platform\Http\Service;

use App\Platform\Http\Contract\AfterResponseInterface;
use ArrayObject;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

#[AsEventListener(event: KernelEvents::TERMINATE, method: 'runAll', priority: self::BEFORE_MAIL)]
#[AsEventListener(event: ConsoleEvents::TERMINATE, method: 'runAll', priority: self::BEFORE_MAIL)]
final readonly class TerminateQueue implements AfterResponseInterface
{
    private const int BEFORE_MAIL = 10;

    /**
     * @var ArrayObject<int, array{string, callable(): void}>
     */
    private ArrayObject $pending;

    public function __construct(
        private LoggerInterface $logger,
    ) {
        $this->pending = new ArrayObject();
    }

    #[Override]
    public function run(string $label, callable $work): void
    {
        $this->pending->append([$label, $work]);
    }

    public function runAll(): void
    {
        $pending = $this->pending->getArrayCopy();
        $this->pending->exchangeArray([]);

        foreach ($pending as [$label, $work]) {
            $this->runOne($label, $work);
        }
    }

    /**
     * @param callable(): void $work
     */
    private function runOne(string $label, callable $work): void
    {
        try {
            $work();
        } catch (Throwable $failure) {
            $this->logger->error('Work after the response failed.', ['work' => $label, 'exception' => $failure]);
        }
    }
}
