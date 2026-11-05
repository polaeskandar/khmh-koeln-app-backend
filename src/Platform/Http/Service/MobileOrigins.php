<?php

declare(strict_types=1);

namespace App\Platform\Http\Service;

use App\Platform\Http\Model\Origins;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class MobileOrigins
{
    /**
     * @var list<string>
     */
    private array $origins;

    public function __construct(#[Autowire(env: 'MOBILE_APP_ORIGINS')] string $list)
    {
        $this->origins = Origins::of($list);
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return $this->origins;
    }
}
