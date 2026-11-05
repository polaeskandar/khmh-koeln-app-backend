<?php

declare(strict_types=1);

namespace App\Tests\Support\Platform\Errors;

use Override;
use Sentry\Event;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

final class RecordingTransport implements TransportInterface
{
    /**
     * @var list<Event>
     */
    private array $events = [];

    #[Override]
    public function send(Event $event): Result
    {
        $this->events[] = $event;

        return new Result(ResultStatus::success(), $event);
    }

    #[Override]
    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }

    /**
     * @return list<Event>
     */
    public function events(): array
    {
        return $this->events;
    }
}
