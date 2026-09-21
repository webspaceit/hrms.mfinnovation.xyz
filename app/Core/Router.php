<?php
// ============================================================
// Router - maps a route name to a Controller+method, or to a
// legacy page file while migration is in progress. Every request
// is dispatched through the front controller (index.php), and
// pretty URLs (/payments) are rewritten by .htaccess.
// ============================================================

namespace App\Core;

class Router {

    /** @var array<string, array{0: class-string, 1: string}> route => [ControllerClass, method] */
    private array $controllers = [];

    /** @var array<string, string> route => legacy page file (relative to project root) */
    private array $legacy = [];

    /**
     * Register a fully-converted route handled by a controller class.
     */
    public function controller(string $route, string $className, string $method = 'index'): void {
        $this->controllers[$route] = [$className, $method];
    }

    /**
     * Register a not-yet-converted route whose page file still renders
     * itself (buildings.php, payments.php, ...). Keeps every old URL
     * working while pages are being converted one by one.
     */
    public function legacy(string $route, string $file): void {
        $this->legacy[$route] = $file;
    }

    /**
     * Dispatch a route name ('', 'dashboard', 'tenant-login', ...).
     */
    public function dispatch(string $route): void {
        $route = trim($route, '/');

        if (isset($this->controllers[$route])) {
            [$class, $method] = $this->controllers[$route];
            if (!class_exists($class)) {
                trigger_error('Controller not found: ' . $class, E_USER_WARNING);
                $this->notFound();
                return;
            }
            $controller = new $class();
            $controller->$method();
            return;
        }

        if (isset($this->legacy[$route])) {
            $file = dirname(__DIR__, 2) . '/' . $this->legacy[$route];
            if (is_file($file)) {
                require $file; // legacy page echoes its own full page
                return;
            }
        }

        $this->notFound();
    }

    private function notFound(): void {
        http_response_code(404);
        echo '<h1>404 - Not Found</h1>';
    }
}