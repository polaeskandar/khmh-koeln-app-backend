<?php

declare(strict_types=1);

namespace App\Platform\Problem\Service;

use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\ConstraintViolationInterface;

final readonly class LengthBounds
{
    /**
     * @return ReadableCollection<string, int>
     */
    public static function of(ConstraintViolationInterface $violation): ReadableCollection
    {
        return $violation->getConstraint() |> self::boundsOf(...) |> self::present(...);
    }

    /**
     * @return array<string, int|null>
     */
    private static function boundsOf(?Constraint $constraint): array
    {
        return $constraint instanceof Length ? ['min' => $constraint->min, 'max' => $constraint->max] : [];
    }

    /**
     * @param array<string, int|null> $bounds
     *
     * @return ReadableCollection<string, int>
     */
    private static function present(array $bounds): ReadableCollection
    {
        return Collections::mapPresent(new ArrayCollection($bounds), map: static fn (?int $bound): ?int => $bound);
    }
}
