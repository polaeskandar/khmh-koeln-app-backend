<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\ValueObject;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Embeddable]
final readonly class RequestSubject
{
    public function __construct(
        #[ORM\Column(name: 'person_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $personId,
        #[ORM\Column(name: 'app_user_id', type: UuidType::NAME, nullable: true)]
        public ?Uuid $accountId,
    ) {
    }
}
