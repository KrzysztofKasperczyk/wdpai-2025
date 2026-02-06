<?php

// Ręczne podłączenie kontrolerów, __DIR__ wskazuje na katalog, w którym znajduje się Routing.php
require_once __DIR__ . '/src/controllers/SecurityController.php';
require_once __DIR__ . '/src/controllers/ToolsController.php';
require_once __DIR__ . '/src/controllers/AccountController.php';
require_once __DIR__ . '/src/controllers/SessionController.php';
require_once __DIR__ . '/src/controllers/GameApiController.php';

class Routing {

    // Prosta mapa: ścieżka -> kontroler + metoda, '' oznacza stronę główną "/"
    public static $routes = [
        ''          => ['controller' => 'ToolsController',   'action' => 'index'],
        'login'     => ['controller' => 'SecurityController','action' => 'login'],
        'register'  => ['controller' => 'SecurityController','action' => 'register'],
        'logout'    => ['controller' => 'SecurityController','action' => 'logout'],
        'tools'     => ['controller' => 'ToolsController',   'action' => 'index'],
        'coin-flip' => ['controller' => 'ToolsController',   'action' => 'coinFlip'],
        'account'   => ['controller' => 'AccountController', 'action' => 'index'],
    ];

    // Główna metoda uruchamiająca routing, wywoływana z index.php
    public static function run(string $path)
    {
        // Podział scieżki na części, np. /session/create -> ['session', 'create']
        $urlParts = explode('/', trim($path, '/'));

        // Pierwszy segment podzielonej ścieżki to nazwa trasy, np. 'login'
        $routeName = $urlParts[0] ?? '';

        // Dynamiczne ścieżki dla session
        // /session/create -> SessionController::create()
        if ($routeName === 'session' && (($urlParts[1] ?? '') === 'create')) {
            $controller = SessionController::getInstance();        // tworzenie singletonu kontrolera sesji
            $controller->create();                                 // i wywołanie metody create() do obsługi /session/create
            return;
        }

        // /session/{uuid} -> SessionController::view($uuid)
        if ($routeName === 'session' && isset($urlParts[1]) && ($urlParts[1] ?? '') !== '') {
            $controller = SessionController::getInstance();       // tworzenie singletonu kontrolera sesji
            $controller->view($urlParts[1]);                     // i wywołanie metody view($uuid) do obsługi /session/{uuid}, gdzie $urlParts[1] to {uuid}
            return;
        }

        // API
        if ($routeName === 'api') {
            $api = GameApiController::getInstance();    // tworzenie singletonu kontrolera API
            $resource = $urlParts[1] ?? '';             // drugi segment to zasób, np. 'session' lub 'coin-flip'
            $action = $urlParts[2] ?? '';               // trzeci segment to akcja, np. 'latest', 'flip' itp.

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
            // Jeśli nie pasuje do żadnej z powyższych tras API, zwróć 404 w formacie JSON
            http_response_code(404);
            header('Content-Type: application/json'); // ustawienie nagłówka, że odpowiedź to JSON
            echo json_encode(['error' => 'API route not found']); // prosta odpowiedź JSON dla nieznanej trasy API
            return;
        }

        // "Aliasowanie" tools/coin-flip -> coin-flip 
        if ($routeName === 'tools') {
            $sub = $urlParts[1] ?? '';
            if ($sub === 'coin-flip') $routeName = 'coin-flip';
        }

        // Jeśli trasa nie istnieje w mapie, zwróć 404
        if (!array_key_exists($routeName, self::$routes)) {
            include 'public/views/404.html';
            return;
        }
        
        // Pobranie nazwy kontrolera z mapy
        $controllerName = self::$routes[$routeName]['controller'];
        // Pobranie nazwy metody z mapy
        $actionName     = self::$routes[$routeName]['action'];

        // Tworzenie singletonu kontrolera
        $controller = $controllerName::getInstance();

        // Wywołanie metody kontrolera
        $controller->$actionName();

    }

}
