<?php

declare(strict_types=1);

namespace App\Interface\Application\Identity\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Language;
use App\Library\Strings\Model\Text;
use App\Platform\Mail\Contract\MailQueue;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AccountMailer
{
    private const string DOMAIN = 'emails';

    public function __construct(
        private MailQueue $queue,
        private TranslatorInterface $translator,
        #[Autowire(env: 'MAILER_FROM')]
        private string $from,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function send(User $user, string $template, array $params = []): void
    {
        $profile = $user->profile();
        $language = $profile->language->value;
        $subject = $this->translator->trans($template . '.subject', $params, self::DOMAIN, $language);

        $email = new TemplatedEmail()
            ->from(Address::create($this->from))
            ->to(new Address($profile->email->value, $profile->displayName->value))
            ->subject($subject)
            ->htmlTemplate(Text::format('emails/%s.html.twig', $template)->toString())
            ->textTemplate(Text::format('emails/%s.txt.twig', $template)->toString())
            ->locale($language)
            ->context([
                ...$params,
                'language' => $language,
                'direction' => $profile->language === Language::Arabic ? 'rtl' : 'ltr',
                'name' => $profile->displayName->value,
            ]);

        $this->queue->queue($email, Text::format('%s:%s', $user->id()->toRfc4122(), $template)->toString());
    }
}
