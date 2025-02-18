<?php
namespace App\Traits;

use Illuminate\Support\Str;

trait HasUuid
{
    /**
     * Boot the trait and attach UUID generation to the creating event.
     */
    protected static function bootHasUuid()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->id = Str::uuid();
            }
        });
    }

    /**
     * Convert UUID string to binary for storage.
     *
     * @param string $uuid
     * @return string
     */
//    public static function toBinaryUuid(string $uuid): string
//    {
//        return hex2bin(str_replace('-', '', $uuid));
//    }
//
//    /**
//     * Convert binary UUID to string for external use.
//     *
//     * @param string $binaryUuid
//     * @return string
//     */
//    public static function toUuidString(string $binaryUuid): string
//    {
//        $hex = bin2hex($binaryUuid);
//        return sprintf(
//            '%s-%s-%s-%s-%s',
//            substr($hex, 0, 8),
//            substr($hex, 8, 4),
//            substr($hex, 12, 4),
//            substr($hex, 16, 4),
//            substr($hex, 20)
//        );
//    }
}
