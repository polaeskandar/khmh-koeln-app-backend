<?php

declare(strict_types=1);

namespace App\Tests\Support\Interface\Api;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class AssignedMeetings
{
    private const string ACTIVE = "status = 'active', opened_at = created_at";

    public Schedule $firstClass;

    public Schedule $thirdClass;

    public Schedule $youthMeeting;

    public Session $firstSession;

    public Session $thirdSession;

    public Session $youthSession;

    public function __construct(
        ContainerInterface $container,
        Service $sundaySchool,
        Stage $first,
        Stage $third,
        Service $youth,
    ) {
        $schedules = new Schedules($container);
        $this->firstClass = $schedules->weekly($sundaySchool, '1st Class meeting', 7, stage: $first);
        $this->thirdClass = $schedules->weekly($sundaySchool, '3rd Class meeting', 7, stage: $third);
        $this->youthMeeting = $schedules->weekly($youth, 'Youth evening', 7);
        $this->firstSession = $schedules->session($this->firstClass, AssignedGroupsFixture::TODAY, self::ACTIVE);
        $this->thirdSession = $schedules->session($this->thirdClass, AssignedGroupsFixture::TODAY, self::ACTIVE);
        $this->youthSession = $schedules->session($this->youthMeeting, AssignedGroupsFixture::TODAY, self::ACTIVE);
    }
}
