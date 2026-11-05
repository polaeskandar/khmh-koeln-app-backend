<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Response;

use App\Workflow\Privacy\Model\AccountData;
use App\Workflow\Privacy\Model\AccountRecord;
use App\Workflow\Privacy\Model\LinkRow;
use App\Workflow\Privacy\Model\PrivacyAcceptance;
use App\Workflow\Privacy\Model\RegistrationView;
use App\Workflow\Privacy\Model\ReportByRow;
use DateTimeImmutable;
use JsonSerializable;
use Override;

final readonly class AccountDataResponse implements JsonSerializable
{
    public function __construct(
        private AccountData $data,
    ) {
    }

    public static function of(?AccountData $data): ?self
    {
        return $data === null ? null : new self($data);
    }

    /**
     * @return array{
     *     kind: string,
     *     exportedAt: DateTimeImmutable,
     *     account: array<string, string|DateTimeImmutable|list<string>|PrivacyAcceptance|RegistrationView|null>,
     *     children: list<LinkRow>,
     *     homeworkReports: list<ReportByRow>,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'kind' => 'account',
            'exportedAt' => $this->data->exportedAt,
            'account' => self::accountOf($this->data->account),
            'children' => $this->data->children,
            'homeworkReports' => $this->data->homeworkReports,
        ];
    }

    /**
     * @return array<string, string|DateTimeImmutable|list<string>|PrivacyAcceptance|RegistrationView|null>
     */
    private static function accountOf(AccountRecord $account): array
    {
        return [
            'id' => $account->identity->id,
            'email' => $account->identity->email,
            'name' => $account->identity->name,
            'language' => $account->identity->language,
            'createdAt' => $account->moments->createdAt,
            'approvedAt' => $account->moments->approvedAt,
            'disabledAt' => $account->moments->disabledAt,
            'privacyNoticeAccepted' => $account->privacy,
            'roles' => $account->roles,
            'registration' => $account->registration,
        ];
    }
}
