<?php


require_once 'AppController.php';

class SecurityController extends AppController {

    public function login() {
        // TODO: Get data from login form
        // check if user exists in database
        // render dashboard after successful login
        return $this->render('login', ['error' => 'Invalid credentials']);
    }

    public function register() {
        return $this->render('register');
    }
}
