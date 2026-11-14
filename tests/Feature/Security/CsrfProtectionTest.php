<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * T7e: every mutating web route must reject a request that has no CSRF token.
 *
 * The test harness normally disables CSRF in the testing environment, so the
 * environment is switched to production for the duration of the requests and
 * restored in a finally block. 419 is the only acceptable answer.
 */
final class CsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_mutating_web_route_returns_419_without_a_token(): void
    {
        $routes = $this->mutatingWebRoutes();
        $this->assertNotEmpty($routes, 'No mutating web routes were found.');

        foreach ($routes as $route) {
            $this->assertContains(
                'web',
                $route->gatherMiddleware(),
                'Mutating route '.$route->getName().' is not in the web middleware group.'
            );
        }

        $originalEnvironment = app()->environment();
        app()->detectEnvironment(static fn (): string => 'production');

        $failures = [];

        try {
            foreach ($routes as $route) {
                $method = $this->mutatingMethod($route);
                $url = route((string) $route->getName(), $this->routeParameters($route));

                $response = $this->call($method, $url);
                $status = $response->getStatusCode();

                if ($status !== 419) {
                    $failures[] = sprintf('%s %s (%s) -> %d', $method, $route->uri(), $route->getName(), $status);
                }
            }
        } finally {
            app()->detectEnvironment(static fn (): string => $originalEnvironment);
        }

        $this->assertSame([], $failures, "Mutating routes accepted a request without a CSRF token:\n".implode("\n", $failures));
    }

    /** @return Collection<int, Route> */
    private function mutatingWebRoutes(): Collection
    {
        return collect(RouteFacade::getRoutes())
            ->filter(fn (Route $route): bool => $route->getName() !== null)
            ->filter(fn (Route $route): bool => in_array('web', $route->gatherMiddleware(), true))
            ->filter(fn (Route $route): bool => count(array_intersect(
                $route->methods(),
                ['POST', 'PUT', 'PATCH', 'DELETE']
            )) > 0)
            ->values();
    }

    private function mutatingMethod(Route $route): string
    {
        return (string) collect($route->methods())->first(
            fn (string $method): bool => in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
        );
    }

    /** @return array<string, mixed> */
    private function routeParameters(Route $route): array
    {
        $parameters = [];

        foreach ($route->parameterNames() as $name) {
            $parameters[$name] = match ($name) {
                'locale' => 'en',
                'path' => 'qa/csrf-probe.txt',
                'token' => 'csrf-probe-token',
                'hash' => sha1('csrf-probe'),
                default => 1,
            };
        }

        return $parameters;
    }
}
