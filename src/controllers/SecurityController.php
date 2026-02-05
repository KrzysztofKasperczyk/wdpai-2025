<?php

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';

class SecurityController extends AppController
{
    private static ?self $instance = null;
    private UserRepository $userRepository;

    private function __construct()
    {
        $this->userRepository = UserRepository::getInstance();
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
            // jeśli już zalogowany, idź do miejsca docelowego (jeśli istnieje)
            if (isset($_SESSION['user_id'])) {
                $this->redirect($this->consumeSafeRedirectAfterLogin());
                return;
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

        // ogólny komunikat
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

        $this->redirect($this->consumeSafeRedirectAfterLogin());
    }

    public function register(): void
    {
        if ($this->isGet()) {
            // jeśli już zalogowany, idź do miejsca docelowego (jeśli istnieje)
            if (isset($_SESSION['user_id'])) {
                $this->redirect($this->consumeSafeRedirectAfterLogin());
                return;
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

        // checklistę reguł hasła pokazujemy po próbie rejestracji
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
        if (!$this->isPasswordStrong($password)) {
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

        $_SESSION['user_id'] = (int)$userId;

        if (function_exists('session_regenerate_id')) {
            session_regenerate_id(true);
        }

        $this->redirect($this->consumeSafeRedirectAfterLogin());
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

        if (isset($_SESSION['user_id'])) {
            $repo = new GameSessionRepository();
            $repo->leaveAllSessions((int)$_SESSION['user_id']);
        }

        session_destroy();
        $this->redirect('/login');
    }

    // -------------------------
    // Redirect helper (anti-loop)
    // -------------------------

    private function consumeSafeRedirectAfterLogin(): string
    {
        $goTo = $_SESSION['redirect_after_login'] ?? '/tools';
        unset($_SESSION['redirect_after_login']);

        // tylko wewnętrzne ścieżki
        if (!is_string($goTo) || $goTo === '' || $goTo[0] !== '/' || str_starts_with($goTo, '//')) {
            return '/tools';
        }

        // nie wracaj na auth pages
        if (
            str_starts_with($goTo, '/login') ||
            str_starts_with($goTo, '/register') ||
            str_starts_with($goTo, '/logout')
        ) {
            return '/tools';
        }

        return $goTo;
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

    private function passwordRulesStatus(string $password): array
    {
        return [
            ['label' => 'At least 9 characters', 'ok' => strlen($password) >= 9],
            ['label' => 'At least 1 digit (0-9)', 'ok' => preg_match('/\d/', $password) === 1],
            ['label' => 'At least 1 uppercase letter (A-Z)', 'ok' => preg_match('/[A-Z]/', $password) === 1],
            ['label' => 'At least 1 special character (e.g. !@#$)', 'ok' => preg_match('/[^a-zA-Z0-9]/', $password) === 1],
        ];
    }
}
