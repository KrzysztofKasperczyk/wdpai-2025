<?php
require_once 'AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';

class SecurityController extends AppController
{
    private static ?self $instance = null;
    private UserRepository $userRepository;

    public function __construct() {
        $this->userRepository = new UserRepository();
    }

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
        // GET -> pokaż formularz
        if ($this->isGet()) {
            return $this->render('login');
        }

        // POST -> obsługa logowania
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            return $this->render('login', ['messages' => 'Fill in all fields']);
        }

        $user = $this->userRepository->getUserByEmail($email);

        if (!$user) {
            return $this->render('login', ['messages' => 'User not found']);
        }

        if (!password_verify($password, $user['password'])) {
            return $this->render('login', ['messages' => 'Wrong password']);
        }

        // TODO: tu możesz zapisać usera w sesji
        // $_SESSION['user_id'] = $user['id'];

        // Po poprawnym logowaniu -> dashboard
        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/dashboard");
        exit();
    }

    public function register()
    {
        // GET -> pokaż formularz
        if ($this->isGet()) {
            return $this->render('register');
        }

        // POST
        $nickname   = $_POST['username']   ?? '';
        $email      = $_POST['email']      ?? '';
        $password   = $_POST['password']   ?? '';
        $password2  = $_POST['password2']  ?? '';

        if (empty($nickname) || empty($email) || empty($password) || empty($password2)) {
            return $this->render('register', ['messages' => 'All fields are required']);
        }

        if ($password !== $password2) {
            return $this->render('register', ['messages' => 'Passwords do not match']);
        }

        // Sprawdź czy email istnieje
        if ($this->userRepository->getUserByEmail($email)) {
            return $this->render('register', ['messages' => 'Email already registered']);
        }

        // Sprawdź czy nickname istnieje
        if ($this->userRepository->getUserByNickname($nickname)) {
            return $this->render('register', ['messages' => 'Nickname already taken']);
        }

        // Utwórz usera
        $this->userRepository->createUser($nickname, $email, $password);

        // Po rejestracji -> login
        header('Location: /login');
        exit();
    }
}
