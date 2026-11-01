<?php

declare(strict_types=1);

namespace App\Tests\Support\Interface\Api\Auth;

use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;

final readonly class Registrations
{
    /**
     * @param array<string, array<string, mixed>> $changes
     *
     * @return array<string, array<string, mixed>>
     */
    public static function parent(array $changes = []): array
    {
        return self::changed([
            'account' => [
                'firstName' => '  Mariam ',
                'lastName' => 'Hanna',
                'email' => 'mariam.hanna@example.org',
                'password' => SignInAccounts::TYPED,
                'language' => 'ar',
            ],
            'registration' => ['registeredAs' => 'parent', 'note' => 'Youssef, 3rd Class', 'serviceIds' => []],
            'privacy' => ['accepted' => true, 'version' => '2026-11'],
        ], $changes);
    }

    /**
     * @param list<string> $serviceIds
     *
     * @return array<string, array<string, mixed>>
     */
    public static function servant(array $serviceIds): array
    {
        return self::parent([
            'account' => ['email' => 'daniel@example.org'],
            'registration' => ['registeredAs' => 'servant', 'note' => 'Youth, Thursdays', 'serviceIds' => $serviceIds],
        ]);
    }

    /**
     * @param array<string, array<string, mixed>> $body
     * @param array<string, array<string, mixed>> $changes
     *
     * @return array<string, array<string, mixed>>
     */
    private static function changed(array $body, array $changes): array
    {
        return array_replace_recursive($body, $changes);
    }
}
