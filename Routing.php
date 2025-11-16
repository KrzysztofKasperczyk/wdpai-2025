<?php

require_once __DIR__ . '/src/controllers/SecurityController.php';
require_once __DIR__ . '/src/controllers/DashboardController.php';

//TODO: Controller singleton
//TODO: przechwytywanie regeg w index
class Routing {

    public static $routes = [
        'login' => ['controller' => 'SecurityController', 'action' => 'login'],
        'register' => ['controller' => 'SecurityController', 'action' => 'register'],
        'dashboard' => ['controller' => 'DashboardController', 'action' => 'index'],
    ];

    public static function run(string $path) {
        switch ($path) {
        case 'login':
        case 'register':
        case 'dashboard':

            $controller = self::$routes[$path]['controller'];
            $action = self::$routes[$path]['action'];
            $id = 0;

            $controllerObj = new $controller;
            $controllerObj->$action($id);
            break;
        default:
            include 'public/views/404.html';
            break;
        }
    }
}