<?php

declare(strict_types=1);

namespace App\Platform\Http\Service;

use App\Library\Strings\Model\Text;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AsEventListener]
final class ApiResponseHeaders
{
    public function __invoke(ResponseEvent $event): void
    {
        $path = Text::of($event->getRequest()->getPathInfo());
        if ($path->toString() !== '/api' && !$path->startsWith('/api/')) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('Cache-Control', 'no-store');
        $headers->set('X-Content-Type-Options', 'nosniff');
    }
}
