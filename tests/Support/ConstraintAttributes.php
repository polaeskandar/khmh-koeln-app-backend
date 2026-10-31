<?php

declare(strict_types=1);

namespace App\Tests\Support;

use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Validator\Constraint;

final readonly class ConstraintAttributes
{
    private const array ROOTS = ['src' => 'App\\', 'tests/Support' => 'App\\Tests\\Support\\'];

    /**
     * @return iterable<string, ReflectionAttribute<Constraint>>
     */
    public static function onProperties(): iterable
    {
        foreach (self::classes() as $class) {
            foreach (new ReflectionClass($class)->getProperties() as $property) {
                $attributes = $property->getAttributes(Constraint::class, ReflectionAttribute::IS_INSTANCEOF);
                foreach ($attributes as $index => $attribute) {
                    yield $class . '::$' . $property->getName() . ' #' . $index => $attribute;
                }
            }
        }
    }

    /**
     * @return iterable<class-string>
     */
    private static function classes(): iterable
    {
        foreach (self::ROOTS as $folder => $namespace) {
            $files = Finder::create()
                ->files()
                ->name('*.php')
                ->in(dirname(__DIR__, levels: 2) . '/' . $folder);
            foreach ($files as $file) {
                $class = $namespace . self::relativeClassName($file);
                if (class_exists($class)) {
                    yield $class;
                }
            }
        }
    }

    private static function relativeClassName(SplFileInfo $file): string
    {
        $folder = $file->getRelativePath() === '' ? '' : strtr($file->getRelativePath(), ['/' => '\\']) . '\\';

        return $folder . $file->getBasename('.php');
    }
}
