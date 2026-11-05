<?php

declare(strict_types=1);

namespace App\Platform\Http\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Nelmio\CorsBundle\Options\ProviderInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

#[AutoconfigureTag('nelmio_cors.options_provider')]
final readonly class CorsOrigins implements ProviderInterface
{
    private const array MOBILE_HEADERS = ['Authorization', 'Content-Type'];

    public function __construct(
        private DevelopmentOrigins $developmentOrigins,
        private MobileOrigins $mobileOrigins,
    ) {
    }

    /**
     * @return array{allow_origin?: list<string>, allow_credentials?: bool, allow_headers?: list<string>}
     */
    #[Override]
    public function getOptions(Request $request): array
    {
        if (!ApiRequest::is($request)) {
            return [];
        }

        $mobile = $this->mobileOrigins->all();

        if (new ArrayCollection($mobile)->contains($request->headers->get('Origin'))) {
            return ['allow_origin' => $mobile, 'allow_credentials' => false, 'allow_headers' => self::MOBILE_HEADERS];
        }

        return ['allow_origin' => $this->developmentOrigins->all()];
    }
}
