<?php

require_once __DIR__ . '/src/controllers/SecurityController.php';
require_once __DIR__ . '/src/controllers/ToolsController.php';
require_once __DIR__ . '/src/controllers/AccountController.php';

class Routing {

    public static $routes = [
        ''          => ['controller' => 'ToolsController',   'action' => 'index'],

        'login'     => ['controller' => 'SecurityController','action' => 'login'],
        'register'  => ['controller' => 'SecurityController','action' => 'register'],
        'logout'    => ['controller' => 'SecurityController','action' => 'logout'],

        'tools'     => ['controller' => 'ToolsController',   'action' => 'index'],
        'coin-flip' => ['controller' => 'ToolsController',   'action' => 'coinFlip'],
        'spin-wheel'=> ['controller' => 'ToolsController',   'action' => 'spinWheel'],
        'roll-dice' => ['controller' => 'ToolsController',   'action' => 'rollDice'],

        'account'   => ['controller' => 'AccountController', 'action' => 'index'],
    ];

    public static function run(string $path) {

        $urlParts = explode('/', trim($path, '/'));
        $routeName = $urlParts[0] ?? '';

        // /tools/coin-flip -> mapujemy na routeName = tools, a drugi segment wybiera akcję
        if ($routeName === 'tools') {
            $sub = $urlParts[1] ?? '';
            if ($sub === 'coin-flip') $routeName = 'coin-flip';
            elseif ($sub === 'spin-wheel') $routeName = 'spin-wheel';
            elseif ($sub === 'roll-dice') $routeName = 'roll-dice';
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
