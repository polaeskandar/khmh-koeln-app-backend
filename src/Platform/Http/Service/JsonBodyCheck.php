<?php

declare(strict_types=1);

namespace App\Platform\Http\Service;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

#[AsEventListener(priority: 34)]
final readonly class JsonBodyCheck
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!ApiRequest::isUnsafe($request) || self::hasJsonBody($request)) {
            return;
        }

        throw new UnsupportedMediaTypeHttpException('invalidRequest');
    }

    private static function hasJsonBody(Request $request): bool
    {
        return $request->getContentTypeFormat() === 'json' && $request->getContent() !== '';
    }
}
