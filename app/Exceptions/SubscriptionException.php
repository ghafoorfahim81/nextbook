<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The subscription stops an action (phase 10): the company is read-only
 * because it expired or was blocked, or the package is full (users, branches).
 *
 * Not an authorization failure — the user may well have the permission — so it
 * answers a form with the page they came from and a message, which the layout
 * shows as a toast; a JSON caller gets a 403 with the same message.
 */
class SubscriptionException extends Exception
{
    public function render(Request $request): SymfonyResponse
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $this->getMessage()], Response::HTTP_FORBIDDEN);
        }

        return back()->with('error', $this->getMessage());
    }
}
