<?php

namespace Database\Seeders\Demo;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use RuntimeException;

/**
 * Posts documents through the application's own HTTP stack.
 *
 * Sales, purchases, receipts and the rest keep their posting logic in the
 * controllers, not in a service a seeder could call, so the only way to create
 * them exactly as the UI does is to send the same request the UI sends: same
 * route, FormRequest, policy, controller and middleware (branch resolution,
 * activity log batching). Only CSRF is bypassed.
 *
 * The clock is frozen at the document's moment for the duration of the request
 * so created_at, "now()" defaults and reversal dates all land on that moment.
 */
final class DemoClient
{
    use InteractsWithAuthentication;
    use MakesHttpRequests;

    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;

        $csrf = array_filter([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            class_exists(\App\Http\Middleware\VerifyCsrfToken::class) ? \App\Http\Middleware\VerifyCsrfToken::class : null,
        ]);

        $this->withoutMiddleware($csrf);
    }

    /**
     * POST (or other verb) to a named route as $user, at $at.
     *
     * Throws when the application refused the request, so a silent validation
     * redirect can never be mistaken for a created document.
     */
    public function send(
        User $user,
        CarbonImmutable $at,
        string $method,
        string $route,
        array $parameters = [],
        array $data = [],
        bool $json = false,
    ): TestResponse {
        Carbon::setTestNow($at);
        \Carbon\CarbonImmutable::setTestNow($at);

        $session = $this->app['session']->driver();
        $session->flush();

        $this->actingAs($user);

        // A few endpoints (landed costs) are unnamed API routes, passed as a path.
        $uri = str_starts_with($route, '/') ? $route : route($route, $parameters, false);
        $server = $json
            ? ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json']
            : ['HTTP_ACCEPT' => 'text/html'];

        $response = $json
            ? $this->call($method, $uri, [], [], [], $server, json_encode($data))
            : $this->call($method, $uri, $data, [], [], $server);

        $this->assertAccepted($response, $route);

        return $response;
    }

    private function assertAccepted(TestResponse $response, string $route): void
    {
        $status = $response->getStatusCode();
        $session = $this->app['session']->driver();
        $problem = null;

        if ($response->exception) {
            $problem = get_class($response->exception) . ': ' . $response->exception->getMessage();

            if (method_exists($response->exception, 'errors')) {
                $problem .= ' ' . json_encode($response->exception->errors(), JSON_UNESCAPED_UNICODE);
            }
        } elseif ($status >= 400) {
            $problem = "HTTP {$status}: " . mb_substr((string) $response->getContent(), 0, 800);
        } elseif ($session->has('errors')) {
            $problem = 'Validation: ' . json_encode($session->get('errors')->getMessages(), JSON_UNESCAPED_UNICODE);
        } elseif ($session->has('error')) {
            $problem = 'Flash error: ' . $session->get('error');
        }

        if ($problem !== null) {
            throw new RuntimeException("[{$route}] {$problem}");
        }
    }
}
