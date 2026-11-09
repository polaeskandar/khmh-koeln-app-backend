<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Language;
use Carbon\CarbonImmutable;
use SensitiveParameter;

final readonly class DataCopyMails
{
    private const string TEMPLATE = 'data_copy';

    private const string PATH = 'data-copy/#token=';

    private const string VALID_UNTIL = 'd.m.Y H:i';

    public function __construct(
        private AccountMailer $mailer,
        private FrontendLinks $links,
    ) {
    }

    public function deliver(?User $recipient, #[SensitiveParameter] string $token, CarbonImmutable $expiresAt): string
    {
        $url = $this->links->in($recipient?->profile()->language ?? Language::German, self::PATH . $token);

        if ($recipient !== null) {
            $this->mailer->send($recipient, self::TEMPLATE, [
                'url' => $url,
                'validUntil' => $expiresAt->format(self::VALID_UNTIL),
            ]);
        }

        return $url;
    }
}
