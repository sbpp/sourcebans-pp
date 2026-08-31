<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Rest;

/**
 * Method + path matcher. Patterns use `{name}` placeholders.
 *
 * @phpstan-type Route array{
 *     method: string,
 *     path: string,
 *     auth: bool,
 *     perm: int,
 *     handler: callable
 * }
 */
final class Router
{
    /** @var list<Route> */
    private array $routes;

    /** @param list<Route> $routes */
    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    /** @return list<Route> */
    public function routes(): array
    {
        return $this->routes;
    }

    /**
     * @return array{route: Route, params: array<string, string>}|array{error: int, allow: list<string>}
     */
    public function match(string $method, string $path): array
    {
        $method = strtoupper($method);
        $path = self::normalize($path);
        $allow = [];
        foreach ($this->routes as $route) {
            $params = self::matchPath($route['path'], $path);
            if ($params === null) {
                continue;
            }
            $allow[] = $route['method'];
            if ($route['method'] === $method) {
                return ['route' => $route, 'params' => $params];
            }
        }
        if ($allow !== []) {
            return ['error' => 405, 'allow' => array_values(array_unique($allow))];
        }
        return ['error' => 404, 'allow' => []];
    }

    public static function normalize(string $path): string
    {
        if ($path === '') {
            return '/';
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        return $path;
    }

    /**
     * @return array<string, string>|null
     */
    private static function matchPath(string $pattern, string $path): ?array
    {
        $pattern = self::normalize($pattern);
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $m): string {
            return '(?P<' . $m[1] . '>[^/]+)';
        }, $pattern);
        if ($regex === null) {
            return null;
        }
        if (preg_match('#^' . $regex . '$#', $path, $m) !== 1) {
            return null;
        }
        $params = [];
        foreach ($m as $k => $v) {
            if (is_string($k)) {
                $params[$k] = $v;
            }
        }
        return $params;
    }
}
