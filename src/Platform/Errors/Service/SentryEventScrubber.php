<?php

declare(strict_types=1);

namespace App\Platform\Errors\Service;

use App\Library\Types\Model\Type;
use App\Platform\Logging\Model\SensitiveKeys;
use Doctrine\Common\Collections\ArrayCollection;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\Frame;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

final readonly class SentryEventScrubber
{
    public function __construct(
        private RequestStack $requests,
        private RouterInterface $router,
    ) {
    }

    public function __invoke(Event $event): Event
    {
        $request = $this->requests->getMainRequest();
        $route = Type::of($request?->attributes->get('_route'))->toStringOrNull();
        $path = $route === null ? null : $this->router->getRouteCollection()->get($route)?->getPath();

        $event->setRequest(['method' => $request?->getMethod(), 'url' => $path]);
        $event->setBreadcrumb([]);
        $event->setUser(null);
        $event->setExtra([]);
        new ArrayCollection($event->getExceptions())->map(self::withoutValues(...));

        return $event;
    }

    private static function withoutValues(ExceptionDataBag $exception): ExceptionDataBag
    {
        $exception->setValue(SensitiveKeys::REDACTED);

        new ArrayCollection($exception->getStacktrace()?->getFrames() ?? [])->map(
            static fn (Frame $frame): Frame => $frame
                ->setVars([])
                ->setPreContext([])
                ->setContextLine(null)
                ->setPostContext([]),
        );

        return $exception;
    }
}
