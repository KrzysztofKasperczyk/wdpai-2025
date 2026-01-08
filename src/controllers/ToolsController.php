<?php
require_once 'AppController.php';

class ToolsController extends AppController
{
    private static ?self $instance = null;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() { throw new \Exception("Cannot unserialize singleton"); }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function index()
    {
        $this->requireAuth();
        return $this->render('tools');
    }

    public function coinFlip()
    {
        $this->requireAuth();
        return $this->render('tool-coin-flip');
    }

    public function spinWheel()
    {
        $this->requireAuth();
        return $this->render('tool-spin-wheel');
    }

    public function rollDice()
    {
        $this->requireAuth();
        return $this->render('tool-roll-dice');
    }
}
