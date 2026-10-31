<?php

declare(strict_types=1);

namespace App\Platform\Problem\Service;

use Symfony\Component\Validator\ConstraintViolationInterface;

final readonly class ViolationCode
{
    private const string UNEXPECTED_FIELD_TEMPLATE = 'This attribute was not expected.';

    public static function of(ConstraintViolationInterface $violation): string
    {
        $template = $violation->getMessageTemplate();

        if ($violation->getConstraint() !== null) {
            return $template;
        }

        return $template === self::UNEXPECTED_FIELD_TEMPLATE ? 'validation.unexpectedField' : 'validation.invalidType';
    }
}
