<?php

declare(strict_types=1);

namespace App\Platform\Http\Service;

use App\Platform\Http\Model\Origins;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[AsEventListener(priority: 35)]
final readonly class OriginCheck
{
    public function __construct(
        #[Autowire(env: 'FRONTEND_URL')]
        private string $frontendUrl,
        private DevelopmentOrigins $developmentOrigins,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (
            !ApiRequest::isUnsafe($request)
            || ApiRequest::isBearer($request)
            || $this->allows($request->headers->get('Origin'))
        ) {
            return;
        }

        throw new AccessDeniedHttpException('forbidden');
    }

    private function allows(?string $origin): bool
    {
        $allowed = new ArrayCollection([Origins::ofUrl($this->frontendUrl), ...$this->developmentOrigins->all()]);

        return $origin === null || $allowed->contains($origin);
    }
}
