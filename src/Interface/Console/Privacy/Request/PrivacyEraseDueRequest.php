<?php

declare(strict_types=1);

namespace App\Interface\Console\Privacy\Request;

use Symfony\Component\Console\Attribute\Option;

final class PrivacyEraseDueRequest
{
    #[Option(description: 'Show what would be erased, then change nothing')]
    public bool $dryRun = false;
}
