<?php

namespace App\Services\Control;

/**
 * Checks a licence from the control center. Everything the ERP needs to trust
 * one: the payload is exactly what was signed, by the holder of the private
 * key that matches the public key it was given.
 *
 * `canonical` must produce the same bytes as the control center's, or no
 * licence would ever verify; tests/Fixtures/licence-golden.json is checked on
 * both sides to keep them so. Do not change one without the other.
 */
class LicenseVerifier
{
    /** @param  array<string, mixed>  $payload */
    public static function verify(array $payload, string $signature, ?string $publicKey): bool
    {
        if (! $publicKey) {
            return false;
        }

        $public = base64_decode($publicKey, true);
        $sig = base64_decode($signature, true);
        if ($public === false || $sig === false || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($sig, self::canonical($payload), $public);
    }

    /** The same bytes whoever builds them: keys sorted at every level, no escaping that varies. */
    public static function canonical(array $payload): string
    {
        $sort = function (array $value) use (&$sort): array {
            foreach ($value as $key => $item) {
                if (is_array($item)) {
                    $value[$key] = $sort($item);
                }
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return $value;
        };

        return json_encode($sort($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
