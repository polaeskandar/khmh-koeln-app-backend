<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Learning\Model\HomeworkAbilities;
use JsonSerializable;
use Override;

final readonly class HomeworkAbilitiesResponse implements JsonSerializable
{
    public function __construct(
        private HomeworkAbilities $can,
    ) {
    }

    /**
     * @return array{
     *     edit: bool,
     *     publish: bool,
     *     unpublish: bool,
     *     archive: bool,
     *     restore: bool,
     *     delete: bool,
     *     viewProgress: bool,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $can = $this->can;

        return [
            'edit' => $can->edit,
            'publish' => $can->publish,
            'unpublish' => $can->lifecycle->unpublish,
            'archive' => $can->lifecycle->archive,
            'restore' => $can->lifecycle->restore,
            'delete' => $can->lifecycle->delete,
            'viewProgress' => $can->viewProgress,
        ];
    }
}
