<?php

declare(strict_types=1);

namespace App\Platform\Mail\Contract;

use Symfony\Component\Mime\Email;

interface MailQueue
{
    public function queue(Email $email, string $label): void;
}
