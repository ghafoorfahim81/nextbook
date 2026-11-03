<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request through only when the control center signed it.
 *
 * What is signed: "provision.v1", the timestamp, a one-time nonce and the raw
 * body, joined by newlines, with the control center's Ed25519 private key. The
 * ERP holds only the public key, so nothing secret is shared and nobody here
 * can forge a request.
 *
 * Refused, in this order and without saying which check failed beyond a status:
 *  - provisioning switched off (404, as if the route did not exist);
 *  - not HTTPS in production (403: the body names a person and a token);
 *  - a missing header, a timestamp outside the window, or a bad signature (401);
 *  - a nonce already seen inside the window (409): a replay.
 *
 * The nonce is remembered only once the signature has verified, so unsigned
 * traffic cannot fill the cache. It is kept twice as long as the window, which
 * is more than enough to cover every timestamp that would still be accepted.
 */
class VerifyControlSignature
{
    public const CONTEXT = 'provision.v1';

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('control.provisioning')) {
            abort(404);
        }

        if (app()->environment('production') && ! $request->isSecure()) {
            abort(403, 'HTTPS is required.');
        }

        $timestamp = (string) $request->header('X-Control-Timestamp');
        $nonce = (string) $request->header('X-Control-Nonce');
        $signature = (string) $request->header('X-Control-Signature');

        if (! ctype_digit($timestamp) || ! preg_match('/^[A-Za-z0-9_-]{16,64}$/', $nonce) || $signature === '') {
            abort(401);
        }

        $window = max(1, (int) config('control.provisioning_window_seconds'));
        if (abs(now()->timestamp - (int) $timestamp) > $window) {
            abort(401, 'The request is too old or too new.');
        }

        if (! self::verify(self::message($timestamp, $nonce, $request->getContent()), $signature, config('control.public_key'))) {
            abort(401);
        }

        // add() is atomic: only the first request with this nonce gets true.
        if (! Cache::add('control-provision-nonce:'.$nonce, 1, $window * 2)) {
            abort(409, 'This request was already received.');
        }

        return $next($request);
    }

    public static function message(string $timestamp, string $nonce, string $body): string
    {
        return implode("\n", [self::CONTEXT, $timestamp, $nonce, $body]);
    }

    public static function verify(string $message, string $signature, ?string $publicKey): bool
    {
        if (! $publicKey) {
            return false;
        }

        $public = base64_decode($publicKey, true);
        $sig = base64_decode($signature, true);
        if ($public === false || $sig === false || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($sig, $message, $public);
    }
}
