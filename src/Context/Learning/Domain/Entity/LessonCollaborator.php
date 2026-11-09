<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'lesson_collaborator', schema: 'learning')]
#[ORM\Index(name: 'lesson_collaborator_user_idx', columns: ['user_id'])]
final class LessonCollaborator
{
    public function __construct(
        #[ORM\Id]
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'lesson_id', nullable: false, onDelete: 'CASCADE')]
        private readonly Lesson $lesson,
        #[ORM\Id]
        #[ORM\Column(name: 'user_id', type: UuidType::NAME)]
        private readonly Uuid $accountId,
    ) {
    }

    public function lesson(): Lesson
    {
        return $this->lesson;
    }

    public function accountId(): Uuid
    {
        return $this->accountId;
    }
}
