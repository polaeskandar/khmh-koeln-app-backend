<?php

declare(strict_types=1);

namespace App\Platform\Http\Service;

use Nelmio\CorsBundle\Options\ProviderInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

#[AutoconfigureTag('nelmio_cors.options_provider')]
final readonly class CorsOrigins implements ProviderInterface
{
    public function __construct(
        private DevelopmentOrigins $developmentOrigins,
    ) {
    }

    /**
     * @return array{allow_origin?: list<string>}
     */
    #[Override]
    public function getOptions(Request $request): array
    {
        return ApiRequest::is($request) ? ['allow_origin' => $this->developmentOrigins->all()] : [];
    }
}
