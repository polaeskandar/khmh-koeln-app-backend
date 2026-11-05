<?php

declare(strict_types=1);

namespace App\Interface\Console\Privacy\Request;

use App\Library\Strings\Model\Text;
use Symfony\Component\Uid\Uuid;

final readonly class PrivacyExportRequest
{
    public function __construct(
        public ?string $person,
        public ?string $account,
        private ?string $output,
    ) {
    }

    public function isValid(): bool
    {
        return ($this->person === null) !== ($this->account === null) && Uuid::isValid($this->id());
    }

    public function isMember(): bool
    {
        return $this->person !== null;
    }

    public function id(): string
    {
        return $this->person ?? $this->account ?? '';
    }

    public function path(): string
    {
        return $this->output ?? Text::format('privacy-export-%s.json', $this->id())->toString();
    }
}
