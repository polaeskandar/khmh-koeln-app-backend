<?php

declare(strict_types=1);

namespace App\Platform\Mail\Service;

use Monolog\Attribute\WithMonologChannel;
use Override;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

#[AutoconfigureTag('mailer.transport_factory')]
#[WithMonologChannel('mailer')]
final class LogTransportFactory extends AbstractTransportFactory
{
    #[Override]
    public function create(Dsn $dsn): TransportInterface
    {
        return new LogTransport($this->logger ?? new NullLogger(), $dsn->getHost(), $this->dispatcher);
    }

    /**
     * @return list<string>
     */
    #[Override]
    protected function getSupportedSchemes(): array
    {
        return ['log'];
    }
}
