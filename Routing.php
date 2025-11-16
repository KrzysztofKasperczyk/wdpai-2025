<?php

require_once __DIR__ . '/src/controllers/SecurityController.php';
require_once __DIR__ . '/src/controllers/DashboardController.php';

class Routing {

    public static $routes = [
        'login'     => ['controller' => 'SecurityController',   'action' => 'login'],
        'register'  => ['controller' => 'SecurityController',   'action' => 'register'],
        'dashboard' => ['controller' => 'DashboardController',  'action' => 'index'],
    ];

    public static function run(string $path) {

        // 1. Rozbijanie ścieżki URL: "dashboard/123" → ["dashboard", "123"]
        $urlParts = explode('/', trim($path, '/'));

        // 2. Pierwszy segment to nazwa akcji w routerze
        $routeName = $urlParts[0] ?? '';

        if (!array_key_exists($routeName, self::$routes)) {
            include 'public/views/404.html';
            return;
        }

        // 3. Drugi segment to opcjonalne ID (np. dashboard/123)
        $id = $urlParts[1] ?? null;

        // 4. Pobranie informacji o kontrolerze i akcji
        $controllerName = self::$routes[$routeName]['controller'];
        $actionName     = self::$routes[$routeName]['action'];

        // 5. Użycie singletonu zamiast new
        $controller = $controllerName::getInstance();

        // 6. Wywołanie akcji z ID
        $controller->$actionName($id);
    }
}
