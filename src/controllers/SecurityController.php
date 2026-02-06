<?php

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';

class SecurityController extends AppController
{
    // Singleton
    private static ?self $instance = null;

    // Zależność do repozytorium użytkownika, logowanie / tworzenie konta
    private UserRepository $userRepository;

    // Prywatny konstruktor
    private function __construct()
    {
        // Pobranie singletonu repozytorium użytkownika
        $this->userRepository = UserRepository::getInstance();
    }

    // Blokowanie klonowania
    private function __clone() {}

    // Blokada odtwarzania z serializacji
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }

    // Metoda dostępu do singletona
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    
    public function login(): void
    {
        // Jeśli GET -> pokaż formularz logowania
        if ($this->isGet()) {
            
            // Jeśli zalogowany, nie pookazuj loginu -> idź dalej
            if (isset($_SESSION['user_id'])) {
                $this->redirect($this->consumeSafeRedirectAfterLogin());
                return;
            }

            // Render widoku logowania
            $this->render('login');
            return;
        }

        // Jeśli nie GET, osbłuż logowanie (POST)
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        // old to dane do ponownego wstawienia w formularzu w przypadku popełnienia błędu
        // Pamiętać o tym że hasła nie powinno się tutaj odtwarzać ze względu na bezpieczeństwo 
        // Ja aktualnie odtwarzam dla wygody testowania
        $old = [
            'email' => $email,
            'password' => $password
        ];

        // Walidacja: email i hasło nie mogą być puste
        if ($email === '' || $password === '') {
            $this->render('login', [
                'messages' => 'Fill in all fields',
                'old' => $old
            ]);
            return;
        }

        // Pobierz użytkownika po emailu
        $user = $this->userRepository->getUserByEmail($email);

        // ogólny komunikat przy błędzie logowania
        if (!$user || !password_verify($password, $user['password'])) {
            $this->render('login', [
                'messages' => 'Invalid email or password',
                'old' => $old
            ]);
            return;
        }

        // Logowanie udane -> zapisz user_id w sesji
        $_SESSION['user_id'] = (int)$user['id'];

        // Regeneracja ID sesji po zalogowaniu, żeby zapobiec atakom typu session fixation
        if (function_exists('session_regenerate_id')) {
            session_regenerate_id(true);
        }
        
        // Przekieruj do miejsca docelowego, jak nie ma to /tools
        $this->redirect($this->consumeSafeRedirectAfterLogin());
    }


    public function register(): void
    {
        // GET -> pokąz formularz rejestracji
        if ($this->isGet()) {
            // jeśli już zalogowany, idź do miejsca docelowego (jeśli istnieje)
            if (isset($_SESSION['user_id'])) {
                $this->redirect($this->consumeSafeRedirectAfterLogin());
                return;
            }
            // Pokaż formularz rejestracji
            $this->render('register');
            return;
        }

        // POST -> obsłuż rejestrację
        $nickname  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password2'] ?? '');

        // żeby nie czyścić pól po błędzie:
        // Pamiętać o tym żeby nie było tu normalnie hasła, aktualnie wygoda dla testowania
        $old = [
            'username' => $nickname,
            'email' => $email,
            'password' => $password,
            'password2' => $password2
        ];

        // Status reguł hasła (pokazywane w UI)
        $pwRules = $this->passwordRulesStatus($password);

        // Wszystkie pola rejestracji wymagane
        if ($nickname === '' || $email === '' || $password === '' || $password2 === '') {
            $this->render('register', [
                'messages' => 'All fields are required',
                'old' => $old,
                'pwRules' => $pwRules
            ]);
            return;
        }

        // Poprawny format email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->render('register', [
                'messages' => 'Invalid email format',
                'old' => $old,
                'pwRules' => $pwRules
            ]);
            return;
        }

        // Nick w zakresie 3-30 znaków
        // Aktualnie bez innych ograniczeń, żeby poczuć wolność życia
        if (strlen($nickname) < 3 || strlen($nickname) > 30) {
            $this->render('register', [
                'messages' => 'Nickname must be 3–30 characters',
                'old' => $old,
                'pwRules' => $pwRules
            ]);
            return;
        }

        // Silne hasło: min 9, cyfra, duża litera, znak specjalny
        if (!$this->isPasswordStrong($password)) {
            $this->render('register', [
                'messages' => 'Password does not meet requirements',
                'old' => $old,
                'pwRules' => $pwRules
            ]);
            return;
        }

        // Oba hasła identyczne
        if ($password !== $password2) {
            $this->render('register', [
                'messages' => 'Passwords do not match',
                'old' => $old,
                'pwRules' => $pwRules
            ]);
            return;
        }

        // Email nie użyty w bazie danych
        if ($this->userRepository->getUserByEmail($email)) {
            $this->render('register', [
                'messages' => 'Email already registered',
                'old' => $old,
                'pwRules' => $pwRules
            ]);
            return;
        }

        // Nick nie użyty w bazie danych
        if ($this->userRepository->getUserByNickname($nickname)) {
            $this->render('register', [
                'messages' => 'Nickname already taken',
                'old' => $old,
                'pwRules' => $pwRules
            ]);
            return;
        }

        // Wszystko ok -> stwórz użytkownika
        $userId = $this->userRepository->createUser($nickname, $email, $password);

        // Automatyczne zalogowanie
        $_SESSION['user_id'] = (int)$userId;


        // Regeneracja ID sesji po zalogowaniu, żeby zapobiec atakom typu session fixation
        if (function_exists('session_regenerate_id')) {
            session_regenerate_id(true);
        }


        // Przekieruj do miejsca docelowego, jak nie ma to /tools
        $this->redirect($this->consumeSafeRedirectAfterLogin());
    }

    public function logout(): void
    {
        // Wyczyszczenie sesji
        $_SESSION = [];

        // Jeśli sesja używa cookies, usuń cookie sesyjne
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

        // Niepotrzebne, zostało zauważone w niedługim terminie przed oddaniem projektu, dla świętego spokoju nie wyrzucam
        if (isset($_SESSION['user_id'])) {
            $repo = new GameSessionRepository();
            $repo->leaveAllSessions((int)$_SESSION['user_id']);
        }

        // Zniszczenie sesji
        session_destroy();

        // Przekierowanie na stronę logowania
        $this->redirect('/login');
    }

    // Redirect helper (anti-loop)
    private function consumeSafeRedirectAfterLogin(): string
    {
        // domyślne miejsce po zalogowaniu to tools
        $goTo = $_SESSION['redirect_after_login'] ?? '/tools';

        // Po użyciu, usuń z sesji, żeby nie było problemów z kolejnym logowaniem
        unset($_SESSION['redirect_after_login']);

        // tylko wewnętrzne ścieżki
        if (!is_string($goTo) || $goTo === '' || $goTo[0] !== '/' || str_starts_with($goTo, '//')) {
            return '/tools';
        }

        // nie wracaj na auth pages (unikanie pętli)
        if (
            str_starts_with($goTo, '/login') ||
            str_starts_with($goTo, '/register') ||
            str_starts_with($goTo, '/logout')
        ) {
            return '/tools';
        }

        return $goTo;
    }

    // Password helpers
    private function isPasswordStrong(string $password): bool
    {
        // Reguły silnego hasła
        $lengthOk = strlen($password) >= 9;
        $digitOk = preg_match('/\d/', $password) === 1;
        $upperOk = preg_match('/[A-Z]/', $password) === 1;
        $specialOk = preg_match('/[^a-zA-Z0-9]/', $password) === 1;

        return $lengthOk && $digitOk && $upperOk && $specialOk;
    }

    private function passwordRulesStatus(string $password): array
    {
        // Sprawdza każdą regułę i zwraca tablicę z informacją, które reguły są spełnione, a które nie (dla UI)
        return [
            ['label' => 'At least 9 characters', 'ok' => strlen($password) >= 9],
            ['label' => 'At least 1 digit (0-9)', 'ok' => preg_match('/\d/', $password) === 1],
            ['label' => 'At least 1 uppercase letter (A-Z)', 'ok' => preg_match('/[A-Z]/', $password) === 1],
            ['label' => 'At least 1 special character (e.g. !@#$)', 'ok' => preg_match('/[^a-zA-Z0-9]/', $password) === 1],
        ];
    }
}
