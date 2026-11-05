<?php

declare(strict_types=1);

namespace App\Platform\Logging\Service;

use App\Library\Collections\Model\Collections;
use App\Platform\Logging\Model\SensitiveText;
use App\Platform\Logging\Model\SensitiveValues;
use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;

#[AsMonologProcessor]
final readonly class RedactingProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: SensitiveText::clean($record->message),
            context: Collections::mapArray($record->context, SensitiveValues::entry(...)),
            extra: Collections::mapArray($record->extra, SensitiveValues::entry(...)),
        );
    }
}
