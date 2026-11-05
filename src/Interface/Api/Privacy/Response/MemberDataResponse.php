<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Response;

use App\Workflow\Privacy\Model\AttendanceRow;
use App\Workflow\Privacy\Model\LinkRow;
use App\Workflow\Privacy\Model\MemberData;
use App\Workflow\Privacy\Model\MembershipRow;
use App\Workflow\Privacy\Model\ReportAboutRow;
use JsonSerializable;
use Override;

final readonly class MemberDataResponse implements JsonSerializable
{
    public function __construct(
        private MemberData $data,
    ) {
    }

    public static function of(?MemberData $data): ?self
    {
        return $data === null ? null : new self($data);
    }

    /**
     * @return array{
     *     kind: string,
     *     exportedAt: string,
     *     member: array{
     *         id: string,
     *         firstName: string,
     *         lastName: string,
     *         displayName: string|null,
     *         status: string,
     *         recordedSince: string,
     *     },
     *     memberships: list<MembershipRow>,
     *     attendance: list<AttendanceRow>,
     *     homeworkReports: list<ReportAboutRow>,
     *     parentAccounts: list<LinkRow>,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $member = $this->data->member;
        $sections = $this->data->sections;

        return [
            'kind' => 'member',
            'exportedAt' => $this->data->exportedAt,
            'member' => [
                'id' => $member->name->id,
                'firstName' => $member->name->firstName,
                'lastName' => $member->name->lastName,
                'displayName' => $member->name->displayName,
                'status' => $member->status,
                'recordedSince' => $member->recordedSince,
            ],
            'memberships' => $sections->memberships,
            'attendance' => $sections->attendance,
            'homeworkReports' => $sections->homeworkReports,
            'parentAccounts' => $sections->parentAccounts,
        ];
    }
}
