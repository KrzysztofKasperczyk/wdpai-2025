<?php

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';

class SecurityController extends AppController
{
    private static ?self $instance = null;
    private UserRepository $userRepository;

    private function __construct()
    {
        $this->userRepository = new UserRepository();
    }

    private function __clone() {}

    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function login(): void
    {
        if ($this->isGet()) {
            // jeśli już zalogowany -> tools
            if (isset($_SESSION['user_id'])) {
                $this->redirect('/tools');
            }

            $this->render('login');
            return;
        }

        // POST
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->render('login', ['messages' => 'Fill in all fields']);
            return;
        }

        $user = $this->userRepository->getUserByEmail($email);

        // zawsze ogólny komunikat (bez zdradzania czy email istnieje)
        if (!$user || !password_verify($password, $user['password'])) {
            $this->render('login', ['messages' => 'Invalid email or password']);
            return;
        }

        // OK -> sesja
        $_SESSION['user_id'] = (int)$user['id'];

        // (opcjonalnie) regeneracja id sesji po zalogowaniu
        if (function_exists('session_regenerate_id')) {
            session_regenerate_id(true);
        }

        $this->redirect('/tools');
    }

    public function register(): void
    {
        if ($this->isGet()) {
            // jeśli już zalogowany -> tools
            if (isset($_SESSION['user_id'])) {
                $this->redirect('/tools');
            }

            $this->render('register');
            return;
        }

        // POST
        $nickname  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password2'] ?? '');

        if ($nickname === '' || $email === '' || $password === '' || $password2 === '') {
            $this->render('register', ['messages' => 'All fields are required']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->render('register', ['messages' => 'Invalid email format']);
            return;
        }

        if (strlen($nickname) < 3 || strlen($nickname) > 30) {
            $this->render('register', ['messages' => 'Nickname must be 3–30 characters']);
            return;
        }

        if (strlen($password) < 6) {
            $this->render('register', ['messages' => 'Password must be at least 6 characters']);
            return;
        }

        if ($password !== $password2) {
            $this->render('register', ['messages' => 'Passwords do not match']);
            return;
        }

        if ($this->userRepository->getUserByEmail($email)) {
            $this->render('register', ['messages' => 'Email already registered']);
            return;
        }

        if ($this->userRepository->getUserByNickname($nickname)) {
            $this->render('register', ['messages' => 'Nickname already taken']);
            return;
        }

        $userId = $this->userRepository->createUser($nickname, $email, $password);

        // po rejestracji: automatycznie logujemy (wygodniej UX)
        $_SESSION['user_id'] = $userId;
        if (function_exists('session_regenerate_id')) {
            session_regenerate_id(true);
        }

        $this->redirect('/tools');
    }

    public function logout(): void
    {
        // wyczyść sesję
        $_SESSION = [];

        // usuń cookie sesji
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        $this->redirect('/login');
    }
}
