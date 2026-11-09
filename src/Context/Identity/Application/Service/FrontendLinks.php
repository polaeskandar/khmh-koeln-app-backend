<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Language;
use App\Library\Strings\Model\Text;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class FrontendLinks
{
    public function __construct(
        #[Autowire(env: 'FRONTEND_URL')]
        private string $frontendUrl,
    ) {
    }

    public function to(User $user, #[SensitiveParameter] string $path): string
    {
        return $this->in($user->profile()->language, $path);
    }

    public function in(Language $language, #[SensitiveParameter] string $path): string
    {
        return Text::format(
            '%s/%s/%s',
            Text::of($this->frontendUrl)->replaceMatches('#/+$#', replacement: '')->toString(),
            $language->value,
            $path,
        )->toString();
    }
}
