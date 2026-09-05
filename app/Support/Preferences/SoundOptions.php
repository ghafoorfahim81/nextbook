<?php

namespace App\Support\Preferences;

final class SoundOptions
{
    public const CATEGORIES = ['notification', 'warning', 'login'];

    private const SOUNDS = [
        'notification' => [
            ['id' => 'notification1', 'name' => 'preferences.notifications.sound.options.notification1', 'filename' => 'notification1.mp3'],
            ['id' => 'notification2', 'name' => 'preferences.notifications.sound.options.notification2', 'filename' => 'notification2.mp3'],
            ['id' => 'notification3', 'name' => 'preferences.notifications.sound.options.notification3', 'filename' => 'notification3.mp3'],
        ],
        'warning' => [
            ['id' => 'warning1', 'name' => 'preferences.notifications.sound.options.warning1', 'filename' => 'warning1.mp3'],
            ['id' => 'windowsxp_warning2', 'name' => 'preferences.notifications.sound.options.windowsxp_warning2', 'filename' => 'windowsxp_warning2.mp3'],
        ],
        'login' => [
            ['id' => 'login1', 'name' => 'preferences.notifications.sound.options.login1', 'filename' => 'login1.mp3'],
            ['id' => 'login2', 'name' => 'preferences.notifications.sound.options.login2', 'filename' => 'login2.mp3'],
        ],
    ];

    private const DEFAULTS = [
        'notification' => 'notification1',
        'warning' => 'warning1',
        'login' => 'login1',
    ];

    /**
     * All sound catalogs keyed by category, for sharing with the frontend.
     */
    public static function grouped(): array
    {
        return array_combine(
            self::CATEGORIES,
            array_map(fn(string $category) => self::forCategory($category), self::CATEGORIES)
        );
    }

    public static function forCategory(string $category): array
    {
        return array_map(
            fn(array $sound) => [
                'id' => $sound['id'],
                'name' => $sound['name'],
                'url' => asset("sounds/{$sound['filename']}"),
            ],
            self::SOUNDS[$category] ?? []
        );
    }

    public static function ids(string $category): array
    {
        return array_column(self::SOUNDS[$category] ?? [], 'id');
    }

    public static function defaultFor(string $category): string
    {
        return self::DEFAULTS[$category] ?? '';
    }
}
