<?php

declare(strict_types=1);

namespace App\Platform\Kernel\Service;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * @param array{APP_ENV: string, APP_DEBUG: bool|string} $context
     */
    public static function fromRuntimeContext(array $context): self
    {
        return new self($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    }

    /**
     * @return list<string>
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
