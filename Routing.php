<?php

require_once __DIR__ . '/src/controllers/SecurityController.php';
require_once __DIR__ . '/src/controllers/ToolsController.php';
require_once __DIR__ . '/src/controllers/AccountController.php';
require_once __DIR__ . '/src/controllers/SessionController.php';
require_once __DIR__ . '/src/controllers/GameApiController.php';

class Routing {

    public static $routes = [
        ''          => ['controller' => 'ToolsController',   'action' => 'index'],
        'login'     => ['controller' => 'SecurityController','action' => 'login'],
        'register'  => ['controller' => 'SecurityController','action' => 'register'],
        'logout'    => ['controller' => 'SecurityController','action' => 'logout'],
        'tools'     => ['controller' => 'ToolsController',   'action' => 'index'],
        'coin-flip' => ['controller' => 'ToolsController',   'action' => 'coinFlip'],
        'account'   => ['controller' => 'AccountController', 'action' => 'index'],
    ];

    public static function run(string $path)
    {
        $urlParts = explode('/', trim($path, '/'));
        $routeName = $urlParts[0] ?? '';

        // ✅ /session/create -> SessionController::create()
        if ($routeName === 'session' && (($urlParts[1] ?? '') === 'create')) {
            $controller = SessionController::getInstance();
            $controller->create();
            return;
        }

        // ✅ /session/{uuid} -> SessionController::view($uuid)
        if ($routeName === 'session' && isset($urlParts[1]) && ($urlParts[1] ?? '') !== '') {
            $controller = SessionController::getInstance();
            $controller->view($urlParts[1]);
            return;
        }

        // ✅ API: /api/...
        if ($routeName === 'api') {
            $api = GameApiController::getInstance();
            $resource = $urlParts[1] ?? '';
            $action = $urlParts[2] ?? '';

            // GET /api/session/latest
            if ($resource === 'session' && $action === 'latest') {
                $api->latest();
                return;
            }

            // GET /api/session/participants
            if ($resource === 'session' && $action === 'participants') {
                $api->participants();
                return;
            }

            // POST /api/coin-flip/flip
            if ($resource === 'coin-flip' && $action === 'flip') {
                $api->coinFlip();
                return;
            }

            // POST /api/session/ping
            if ($resource === 'session' && $action === 'ping') {
                $api->ping();
                return;
            }

            // POST /api/session/leave
            if ($resource === 'session' && $action === 'leave') {
                $api->leave();
                return;
            }

            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'API route not found']);
            return;
        }

        // 🔹 MAPOWANIE: /tools/* -> konkretne akcje
        if ($routeName === 'tools') {
            $sub = $urlParts[1] ?? '';
            if ($sub === 'coin-flip') $routeName = 'coin-flip';
        }

        if (!array_key_exists($routeName, self::$routes)) {
            include 'public/views/404.html';
            return;
        }

        $controllerName = self::$routes[$routeName]['controller'];
        $actionName     = self::$routes[$routeName]['action'];

        $controller = $controllerName::getInstance();
        $controller->$actionName();

    }

}
