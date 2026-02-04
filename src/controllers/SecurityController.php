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
            if (isset($_SESSION['user_id'])) {
                $this->redirect('/tools');
            }

            $this->render('login');
            return;
        }

        // POST
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        // żeby nie czyścić pól po błędzie:
        $old = [
            'email' => $email,
            'password' => $password
        ];

        if ($email === '' || $password === '') {
            $this->render('login', [
                'messages' => 'Fill in all fields',
                'old' => $old
            ]);
            return;
        }

        $user = $this->userRepository->getUserByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->render('login', [
                'messages' => 'Invalid email or password',
                'old' => $old
            ]);
            return;
        }

        $_SESSION['user_id'] = (int)$user['id'];

        if (function_exists('session_regenerate_id')) {
            session_regenerate_id(true);
        }

        $this->redirect('/tools');
    }

    public function register(): void
    {
        if ($this->isGet()) {
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

        // żeby nie czyścić pól po błędzie:
        $old = [
            'username' => $nickname,
            'email' => $email,
            'password' => $password,
            'password2' => $password2
        ];

        // pokaż checklistę wymagań hasła zawsze, gdy user próbuje się zarejestrować
        $pwRules = $this->passwordRulesStatus($password);

        if ($nickname === '' || $email === '' || $password === '' || $password2 === '') {
            $this->render('register', [
                'messages' => 'All fields are required',
                'old' => $old,
                'pwRules' => $pwRules
            ]);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->render('register', [
                'messages' => 'Invalid email format',
                'old' => $old,
                'pwRules' => $pwRules
            ]);
            return;
        }

        if (strlen($nickname) < 3 || strlen($nickname) > 30) {
            $this->render('register', [
                'messages' => 'Nickname must be 3–30 characters',
                'old' => $old,
                'pwRules' => $pwRules
            ]);
            return;
        }

        // ✅ silna walidacja hasła: min 9, cyfra, duża litera, znak specjalny
        $pwOk = $this->isPasswordStrong($password);
        if (!$pwOk) {
            $this->render('register', [
                'messages' => 'Password does not meet requirements',
                'old' => $old,
                'pwRules' => $pwRules
            ]);
            return;
        }

        if ($password !== $password2) {
            $this->render('register', [
                'messages' => 'Passwords do not match',
                'old' => $old,
                'pwRules' => $pwRules
            ]);
            return;
        }

        if ($this->userRepository->getUserByEmail($email)) {
            $this->render('register', [
                'messages' => 'Email already registered',
                'old' => $old,
                'pwRules' => $pwRules
            ]);
            return;
        }

        if ($this->userRepository->getUserByNickname($nickname)) {
            $this->render('register', [
                'messages' => 'Nickname already taken',
                'old' => $old,
                'pwRules' => $pwRules
            ]);
            return;
        }

        $userId = $this->userRepository->createUser($nickname, $email, $password);

        $_SESSION['user_id'] = $userId;
        if (function_exists('session_regenerate_id')) {
            session_regenerate_id(true);
        }

        $this->redirect('/tools');
    }

    public function logout(): void
    {
        $_SESSION = [];

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

    // -------------------------
    // Password helpers
    // -------------------------

    private function isPasswordStrong(string $password): bool
    {
        $lengthOk = strlen($password) >= 9;
        $digitOk = preg_match('/\d/', $password) === 1;
        $upperOk = preg_match('/[A-Z]/', $password) === 1;
        $specialOk = preg_match('/[^a-zA-Z0-9]/', $password) === 1;

        return $lengthOk && $digitOk && $upperOk && $specialOk;
    }

    /**
     * Zwraca statusy reguł do wyświetlenia w UI (zielone/czerwone)
     */
    private function passwordRulesStatus(string $password): array
    {
        return [
            [
                'label' => 'At least 9 characters',
                'ok' => strlen($password) >= 9
            ],
            [
                'label' => 'At least 1 digit (0-9)',
                'ok' => preg_match('/\d/', $password) === 1
            ],
            [
                'label' => 'At least 1 uppercase letter (A-Z)',
                'ok' => preg_match('/[A-Z]/', $password) === 1
            ],
            [
                'label' => 'At least 1 special character (e.g. !@#$)',
                'ok' => preg_match('/[^a-zA-Z0-9]/', $password) === 1
            ],
        ];
    }
}
