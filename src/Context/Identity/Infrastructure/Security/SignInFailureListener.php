<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Platform\Problem\Contract\ProblemResponderInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

#[AsEventListener]
final readonly class SignInFailureListener
{
    public function __construct(
        private ProblemResponderInterface $problems,
    ) {
    }

    public function __invoke(LoginFailureEvent $event): void
    {
        $event->setResponse($this->problems->respond(SignInRefusal::of($event->getException())));
    }
}
