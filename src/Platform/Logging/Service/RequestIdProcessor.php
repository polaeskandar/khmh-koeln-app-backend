<?php

declare(strict_types=1);

namespace App\Platform\Logging\Service;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsMonologProcessor]
final readonly class RequestIdProcessor
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $request = $this->requests->getMainRequest();
        $id = $request === null ? '' : RequestIds::of($request);

        if ($id === '') {
            return $record;
        }

        return $record->with(extra: [...$record->extra, 'request_id' => $id]);
    }
}
