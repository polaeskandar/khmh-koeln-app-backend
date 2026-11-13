<?php

declare(strict_types=1);

namespace App\Context\Access\Domain\Enum;

enum Resource: string
{
    case Users = 'users';
    case Roles = 'roles';
    case Services = 'services';
    case Stages = 'stages';
    case Sessions = 'sessions';
    case Attendance = 'attendance';
    case Members = 'members';
    case Lessons = 'lessons';
    case Homework = 'homework';
    case ParentPortal = 'parentPortal';
}
