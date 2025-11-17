<?php
require_once 'AppController.php';

 

class SecurityController extends AppController
{

    // ======= LOKALNA "BAZA" UŻYTKOWNIKÓW =======
    private static array $users = [
        [
            'email' => 'anna@example.com',
            'password' => '$2y$10$wz2g9JrHYcF8bLGBbDkEXuJQAnl4uO9RV6cWJKcf.6uAEkhFZpU0i', // test123
            'first_name' => 'Anna'
        ],
        [
            'email' => 'bartek@example.com',
            'password' => '$2y$10$fK9rLobZK2C6rJq6B/9I6u6Udaez9CaRu7eC/0zT3pGq5piVDsElW', // haslo456
            'first_name' => 'Bartek'
        ],
        [
            'email' => 'celina@example.com',
            'password' => '$2y$10$Cq1J6YMGzRKR6XzTb3fDF.6sC6CShm8kFgEv7jJdtyWkhC1GuazJa', // qwerty
            'first_name' => 'Celina'
        ],
    ];

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
        if (!$this->isPost()) {
           return $this->render('login', ['error' => 'Invalid credentials']);
        }
        
        

        // zrobic dekorator
        
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

          if (empty($email) || empty($password)) {
            return $this->render('login', ['messages' => 'Fill all fields']);
        }

        $userRow = null;
        foreach (self::$users as $u) {
            if (strcasecmp($u['email'], $email) === 0) {
                $userRow = $u;
                break;
            }
        }

        if (!$userRow) {
            return $this->render('login', ['messages' => 'User not found']);
        }

        if (!password_verify($password, $userRow['password'])) {
            return $this->render('login', ['messages' => 'Wrong password']);
        }

        // check if user exists in database
        // render dashboard after successful login

        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/dashboard");

        
    }

    public function register()
    {
        if (!$this->isPost()) {
           return $this->render('register');
        }
        
        var_dump($_POST);

        return $this->render('login');
        
    }
}


//rozwinac formularz rejestracji, przekazywanie danych