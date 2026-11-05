<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\Learning\Model\LessonOption;
use App\Workflow\Learning\Model\LessonOptions;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class LessonOptionsResponse
{
    /**
     * @param list<LessonOptionResponse> $options
     * @param list<AccountReference> $servants
     */
    public function __construct(
        public string $today,
        public array $options,
        public array $servants,
    ) {
    }

    public static function of(LessonOptions $options): self
    {
        $optionOf = static fn (LessonOption $option): LessonOptionResponse => new LessonOptionResponse($option);

        return new self(
            $options->today,
            new ArrayCollection($options->options)->map($optionOf)->getValues(),
            $options->servants,
        );
    }
}
