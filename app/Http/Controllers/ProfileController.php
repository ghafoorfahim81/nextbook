<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return Inertia::render('Profile/Index', [
            'confirmsTwoFactorAuthentication' => config('fortify.confirms_two_factor_authentication', false),
            'sessions' => $this->sessions($request),
        ]);
    }

    protected function sessions(Request $request): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        return collect(
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $request->user()->getAuthIdentifier())
                ->orderBy('last_activity', 'desc')
                ->get()
        )->map(function ($session) use ($request) {
            $ua = $session->user_agent ?? '';

            return (object) [
                'agent' => [
                    'is_desktop' => !preg_match('/(Mobile|Android|iPhone|iPad)/i', $ua),
                    'platform'   => $this->parsePlatform($ua),
                    'browser'    => $this->parseBrowser($ua),
                ],
                'ip_address'       => $session->ip_address,
                'is_current_device' => $session->id === $request->session()->getId(),
                'last_active'      => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
            ];
        })->all();
    }

    private function parsePlatform(string $ua): string
    {
        if (str_contains($ua, 'Windows')) return 'Windows';
        if (str_contains($ua, 'Macintosh')) return 'macOS';
        if (str_contains($ua, 'Linux')) return 'Linux';
        if (str_contains($ua, 'Android')) return 'Android';
        if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) return 'iOS';
        return 'Unknown';
    }

    private function parseBrowser(string $ua): string
    {
        if (str_contains($ua, 'Edg/')) return 'Edge';
        if (str_contains($ua, 'OPR/') || str_contains($ua, 'Opera')) return 'Opera';
        if (str_contains($ua, 'Chrome')) return 'Chrome';
        if (str_contains($ua, 'Firefox')) return 'Firefox';
        if (str_contains($ua, 'Safari')) return 'Safari';
        return 'Unknown';
    }
}
