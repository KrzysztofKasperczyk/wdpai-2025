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

        $urlParts = explode('/', trim($path, '/'));
        $routeName = $urlParts[0] ?? '';

        if (!array_key_exists($routeName, self::$routes)) {
            include 'public/views/404.html';
            return;
        }

        $id = $urlParts[1] ?? null;

        $controllerName = self::$routes[$routeName]['controller'];
        $actionName     = self::$routes[$routeName]['action'];

        $controller = $controllerName::getInstance();

        // jeśli jest ID i metoda je przyjmuje
        if ($id !== null && $routeName === 'dashboard') {
            $controller->$actionName((int)$id);
        } else {
            $controller->$actionName();
        }
    }
}
