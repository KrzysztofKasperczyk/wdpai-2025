<?php
require_once 'AppController.php';

class ToolsController extends AppController
{

    // Singleton
    private static ?self $instance = null;

    private function __construct() {}

    // Blokada klonowania singletona
    private function __clone() {}

    // Blokada unserializowania singletona
    public function __wakeup() { throw new \Exception("Cannot unserialize singleton"); }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }


    // Akcja dla "/" i "/tools"
    public function index()
    {
        // Wymaga zalogowanego użytkownika
        // Jeśli nie -> redirect do /login
        $this->requireAuth();
        return $this->render('tools');
    }

    // Akcja dla "/coin-flip" i "/tools/coin-flip"
    public function coinFlip()
    {
        // Tylko dla zalogowanych
        $this->requireAuth();
        return $this->render('tool-coin-flip');
    }

}
