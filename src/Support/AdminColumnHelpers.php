<?php

namespace Tolery\AiCad\Support;

use BackedEnum;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Tiny helpers used by the admin Livewire tables to keep their column
 * `render` closures readable and DRY.
 */
class AdminColumnHelpers
{
    /**
     * Render a team cell as a link to the host app's admin team page when the
     * route is configured AND registered. Falls back to escaped plain text
     * otherwise so the package keeps working in a host that doesn't expose a
     * team admin page.
     *
     * `config('ai-cad.admin_team_route_name')` may be:
     *   - a string  — same route for every team
     *   - an array  — keyed by team type (e.g. ['client' => 'companies.client.detail'])
     *                 with an optional 'default' fallback.
     */
    public static function teamLinkOrName(mixed $team): string
    {
        if (! $team) {
            return '-';
        }

        $name = (string) ($team->name ?? '-');
        $routeName = self::resolveTeamRouteName($team);

        if (! $routeName || ! Route::has($routeName)) {
            return e($name);
        }

        $routeKey = method_exists($team, 'getRouteKey') ? $team->getRouteKey() : ($team->id ?? null);

        return '<a href="'.e(route($routeName, ['team' => $routeKey])).'" class="text-blue-600 hover:underline">'.e($name).'</a>';
    }

    /**
     * Probe the configured storage disk for `temporaryUrl` support, swallowing
     * any SDK misconfiguration so a single bad env var (missing AWS region,
     * etc.) can't 500 an entire admin table. Falls back to "no temporary URL".
     */
    public static function diskSupportsTemporaryUrl(string $disk): bool
    {
        try {
            return method_exists(Storage::disk($disk)->getAdapter(), 'temporaryUrl');
        } catch (Throwable) {
            return false;
        }
    }

    private static function resolveTeamRouteName(mixed $team): ?string
    {
        $config = config('ai-cad.admin_team_route_name');

        if (is_string($config)) {
            return $config;
        }

        if (is_array($config)) {
            $type = $team->type ?? null;
            $key = strtolower((string) ($type instanceof BackedEnum ? $type->value : $type));

            return $config[$key] ?? $config['default'] ?? null;
        }

        return null;
    }
}
