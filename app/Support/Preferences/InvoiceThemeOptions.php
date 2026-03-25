<?php

namespace App\Support\Preferences;

final class InvoiceThemeOptions
{
    public const DEFAULT = 'format1';

    private const THEMES = [
        ['id' => 'format1', 'name' => 'Format 1', 'filename' => 'format1.jpeg'],
        ['id' => 'format2', 'name' => 'Format 2', 'filename' => 'format2.jpeg'],
        ['id' => 'format3', 'name' => 'Format 3', 'filename' => 'format3.jpeg'],
        ['id' => 'format4', 'name' => 'Format 4', 'filename' => 'format4.jpeg'],
        ['id' => 'format5', 'name' => 'Format 5', 'filename' => 'format5.jpeg'],
    ];

    public static function all(): array
    {
        return array_map(
            fn(array $theme) => [
                'id' => $theme['id'],
                'name' => $theme['name'],
                'preview_url' => asset("images/invoice_formats/{$theme['filename']}"),
            ],
            self::THEMES
        );
    }

    public static function ids(): array
    {
        return array_column(self::THEMES, 'id');
    }
}
