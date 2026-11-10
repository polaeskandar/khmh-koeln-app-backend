<?php

declare(strict_types=1);

namespace App\Context\Privacy\Application\Service;

use App\Context\Privacy\Domain\Entity\CopyLink;
use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Context\Privacy\Domain\Repository\CopyLinkRepositoryInterface;
use App\Context\Privacy\Domain\Repository\DataRequestRepositoryInterface;
use App\Context\Privacy\Domain\ValueObject\CopyableSubject;
use App\Context\Privacy\Domain\ValueObject\IssuedCopyLink;
use App\Context\Privacy\Domain\ValueObject\RequestSubject;
use App\Library\Checksum\Model\Checksum;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use DateInterval;
use SensitiveParameter;
use Symfony\Component\String\ByteString;
use Symfony\Component\Uid\Uuid;

final readonly class CopyLinks
{
    private const string LIFETIME = 'P7D';

    private const string TOKEN_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

    private const int TOKEN_LENGTH = 43;

    public function __construct(
        private DataRequestRepositoryInterface $requests,
        private CopyLinkRepositoryInterface $links,
        private DataRequestLifecycle $lifecycle,
        private Calendar $calendar,
    ) {
    }

    public function subjectOf(Uuid $requestId): CopyableSubject
    {
        return CopyableSubject::of($this->request($requestId));
    }

    public function issue(Uuid $requestId, ?Uuid $recipientId): IssuedCopyLink
    {
        $request = $this->request($requestId);
        CopyableSubject::of($request);

        $token = ByteString::fromRandom(self::TOKEN_LENGTH, self::TOKEN_ALPHABET)->toString();
        $expiresAt = $this->calendar->now()->add(new DateInterval(self::LIFETIME));

        $this->links->replaceUnused(
            new CopyLink($request, Checksum::sha256($token), $expiresAt->toDateTimeImmutable(), $recipientId),
        );

        return new IssuedCopyLink($token, $expiresAt);
    }

    public function spend(#[SensitiveParameter] string $token): ?RequestSubject
    {
        $now = $this->calendar->now()->toDateTimeImmutable();

        return $this->links->spend(Checksum::sha256($token), $now, function (DataRequest $request) use (
            $now,
        ): RequestSubject {
            $this->lifecycle->complete($request, $now, by: null, outcome: null);

            return $request->subject();
        });
    }

    private function request(Uuid $id): DataRequest
    {
        return $this->requests->find($id) ?? throw NotFound::of('request');
    }
}
