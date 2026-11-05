<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

final readonly class DataRequestDetail
{
    public function __construct(
        public string $today,
        public DataRequestRow $request,
        public ?RequestMember $member,
        public ?RequestAccount $account,
    ) {
    }
}
