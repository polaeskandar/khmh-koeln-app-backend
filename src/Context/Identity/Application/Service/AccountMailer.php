<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Enum\Language;
use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Strings\Model\Text;
use App\Platform\Mail\Contract\MailQueueInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AccountMailer
{
    private const string DOMAIN = 'emails';

    private const string TEST_TEMPLATE = 'test';

    public function __construct(
        private AccountRepositoryInterface $accounts,
        private MailQueueInterface $queue,
        private TranslatorInterface $translator,
        #[Autowire(env: 'MAILER_FROM')]
        private string $from,
    ) {
    }

    public function sendTest(string $email): void
    {
        $this->send($this->accounts->findByEmail($email) ?? throw NotFound::of('account'), self::TEST_TEMPLATE);
    }

    /**
     * @param array<string, string> $params
     */
    public function send(Account $account, string $template, array $params = []): void
    {
        $profile = $account->profile();
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

        $this->queue->queue($email, Text::format('%s:%s', $account->id()->toRfc4122(), $template)->toString());
    }
}
