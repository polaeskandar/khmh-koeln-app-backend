<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\People\Domain\ValueObject\ImportLine;
use App\Context\People\Domain\ValueObject\ImportPlan;
use App\Library\Strings\Model\Text;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ImportReport
{
    /**
     * @param list<string> $errors
     * @param list<array{string, list<string>}> $sections
     */
    public function __construct(
        public array $errors,
        public array $sections,
    ) {
    }

    public static function of(ImportPlan $plan, string $done): self
    {
        if (!$plan->isValid()) {
            return new self(self::texts($plan->errors), []);
        }

        $people = new ArrayCollection($plan->people)->count();
        $memberships = new ArrayCollection($plan->created)->count();
        $title = Text::format('%s %d people with %d memberships', $done, $people, $memberships)->toString();
        $created = [$title, self::texts($plan->created)];

        return new self([], $plan->skipped === [] ? [$created] : [$created, ['Skipped', self::texts($plan->skipped)]]);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * @param list<ImportLine> $lines
     *
     * @return list<string>
     */
    private static function texts(array $lines): array
    {
        return new ArrayCollection($lines)->map(
            static fn (ImportLine $line): string => Text::format('line %d: %s', $line->line, $line->text)->toString(),
        )->getValues();
    }
}
