<?php

require_once 'AppController.php';

class SecurityController extends AppController
{
    private static $instance = null;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function login()
    {
        // TODO: Get data from login form
        // check if user exists in database
        // render dashboard after successful login
        return $this->render('login', ['error' => 'Invalid credentials']);
    }

    public function register()
    {
        return $this->render('register');
    }
}
