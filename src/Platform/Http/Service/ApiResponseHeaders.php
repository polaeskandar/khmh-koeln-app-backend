<?php

declare(strict_types=1);

namespace App\Platform\Http\Service;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AsEventListener]
final class ApiResponseHeaders
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!ApiRequest::is($event->getRequest())) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('Cache-Control', 'no-store');
        $headers->set('X-Content-Type-Options', 'nosniff');
    }
}
