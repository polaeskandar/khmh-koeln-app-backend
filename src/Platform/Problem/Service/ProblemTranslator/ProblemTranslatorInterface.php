<?php

declare(strict_types=1);

namespace App\Platform\Problem\Service\ProblemTranslator;

use App\Platform\Problem\Model\Problem;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Throwable;

/**
 * @template TException of Throwable
 */
#[AutoconfigureTag(self::TAG)]
interface ProblemTranslatorInterface
{
    public const string TAG = 'app.problem_translator';

    public function supports(Throwable $exception): bool;

    /**
     * @param TException $exception
     */
    public function translate(Throwable $exception): Problem;
}
