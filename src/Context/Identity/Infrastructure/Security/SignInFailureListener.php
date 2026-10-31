<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Platform\Problem\Contract\ProblemResponder;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

#[AsEventListener]
final readonly class SignInFailureListener
{
    public function __construct(
        private ProblemResponder $problems,
    ) {
    }

    public function __invoke(LoginFailureEvent $event): void
    {
        $exception = $event->getException();

        $refusal = $exception instanceof CustomUserMessageAccountStatusException
            ? new AccessDeniedHttpException($exception->getMessageKey())
            : new UnauthorizedHttpException('Cookie', 'auth.invalidCredentials');

        $event->setResponse($this->problems->respond($refusal));
    }
}
