<?php

declare(strict_types=1);

namespace App\Platform\Logging\Service;

use App\Library\Strings\Model\Text;
use App\Library\Types\Model\Type;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

final readonly class RequestIds
{
    public const string HEADER = 'X-Request-Id';

    public const string ATTRIBUTE = '_request_id';

    private const string VALID = '/\A[A-Za-z0-9-]{1,64}\z/';

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 4096)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $sent = (string) $request->headers->get(self::HEADER, '');

        $request->attributes->set(
            self::ATTRIBUTE,
            Text::of($sent)->matches(self::VALID) ? $sent : Uuid::v7()->toRfc4122(),
        );
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -4096)]
    public function onResponse(ResponseEvent $event): void
    {
        $event->getResponse()->headers->set(self::HEADER, self::of($event->getRequest()));
    }

    public static function of(Request $request): string
    {
        return Type::of($request->attributes->get(self::ATTRIBUTE))->toStringOrNull() ?? '';
    }
}
