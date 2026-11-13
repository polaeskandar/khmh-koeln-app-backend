<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Domain\ValueObject\Username;
use App\Library\Strings\Model\Text;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RateLimiter\AbstractRequestRateLimiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

final class SignInRateLimiter extends AbstractRequestRateLimiter
{
    public function __construct(
        #[Autowire(service: 'limiter.sign_in_client')]
        private readonly RateLimiterFactoryInterface $clients,
        #[Autowire(service: 'limiter.sign_in_account')]
        private readonly RateLimiterFactoryInterface $accounts,
    ) {
    }

    /**
     * @return list<LimiterInterface>
     */
    #[Override]
    protected function getLimiters(Request $request): array
    {
        $client = $request->getClientIp() ?? '';
        $username = Username::normalized($request->attributes->getString(SecurityRequestAttributes::LAST_USERNAME));

        return [
            $this->clients->create($client),
            $this->accounts->create(Text::format('%s-%s', $username, $client)->toString()),
        ];
    }
}
