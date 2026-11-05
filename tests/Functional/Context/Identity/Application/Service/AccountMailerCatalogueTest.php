<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Application\Service;

use App\Tests\Support\JsonBody;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;

final class AccountMailerCatalogueTest extends KernelTestCase
{
    private const array LANGUAGES = ['en', 'de', 'ar'];

    public function testEveryLanguageHasEveryKey(): void
    {
        $english = self::keysOf('en');

        foreach (self::LANGUAGES as $language) {
            self::assertEqualsCanonicalizing($english, self::keysOf($language), $language);
        }
    }

    /**
     * @return list<array-key>
     */
    private static function keysOf(string $language): array
    {
        return array_keys(JsonBody::asArray(
            Yaml::parseFile(self::root() . '/translations/emails.' . $language . '.yaml'),
            $language,
        ));
    }

    #[DataProvider('templates')]
    public function testEveryTemplateRendersInEveryLanguage(string $template): void
    {
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $switcher = self::getContainer()->get('translation.locale_switcher');
        self::assertInstanceOf(LocaleSwitcher::class, $switcher);

        foreach (self::LANGUAGES as $language) {
            $rendered = $switcher->runWithLocale($language, static fn (): string => $twig->render($template, [
                'language' => $language,
                'direction' => $language === 'ar' ? 'rtl' : 'ltr',
                'name' => 'Sara',
            ]));

            self::assertStringNotContainsString('test.body', $rendered, $template . ' ' . $language);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function templates(): iterable
    {
        $paths = glob(self::root() . '/templates/emails/*.twig');

        foreach ($paths === false ? [] : $paths as $path) {
            yield basename($path) => ['emails/' . basename($path)];
        }
    }

    private static function root(): string
    {
        return dirname(__DIR__, levels: 6);
    }
}
