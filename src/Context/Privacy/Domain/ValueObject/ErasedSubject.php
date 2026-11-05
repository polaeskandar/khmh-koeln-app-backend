<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\ValueObject;

use App\Context\Privacy\Domain\Enum\ErasedSubjectKind;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class ErasedSubject
{
    public function __construct(
        #[ORM\Column(name: 'erased_subject_kind', length: 7, nullable: true, enumType: ErasedSubjectKind::class)]
        public ?ErasedSubjectKind $kind = null,
        #[ORM\Column(name: 'erased_subject_group', length: 120, nullable: true)]
        public ?string $group = null,
    ) {
    }
}
