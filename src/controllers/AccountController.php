<?php

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';

class AccountController extends AppController
{
    // Singleton
    private static ?self $instance = null;
    private UserRepository $userRepository;

    private function __construct()
    {
        $this->userRepository = UserRepository::getInstance();
    }

    private function __clone() {}
    public function __wakeup() { throw new \Exception("Cannot unserialize singleton"); }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * /account
     * Pokazuje dane profilu oraz statystyki zalogowanego użytkownika.
     */
    public function index(): void
    {
        $this->requireAuth();

        // Pobierz ID usera z sesji
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->redirect('/login');
        }

        // Pobierz dane użytkownika z DB
        $user = $this->userRepository->getUserById($userId);

        // Statystyki usera z tabeli user_stats (jeśli brak -> repo zwraca 0/0)
        $statsRow = $this->userRepository->getStatsByUserId($userId);

        // Mapowanie do formatu pod widok
        $stats = [
            'arguments_won' => (int)($statsRow['wins'] ?? 0),
            'total_draws'   => (int)($statsRow['total_draws'] ?? 0),
        ];

        // Render widoku public/views/account.html
        $this->render('account', [
            'user' => $user,
            'stats' => $stats
        ]);
    }
}
