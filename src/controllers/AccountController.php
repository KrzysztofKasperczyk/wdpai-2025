<?php

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';

class AccountController extends AppController
{
    private static ?self $instance = null;
    private UserRepository $userRepository;

    private function __construct()
    {
        $this->userRepository = new UserRepository();
    }

    private function __clone() {}
    public function __wakeup() { throw new \Exception("Cannot unserialize singleton"); }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function index(): void
    {
        $this->requireAuth();

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->redirect('/login');
        }

        $user = $this->userRepository->getUserById($userId);

        // stats z DB (jeśli brak rekordu -> 0/0)
        $statsRow = $this->userRepository->getStatsByUserId($userId);

        $stats = [
            'arguments_won' => (int)($statsRow['wins'] ?? 0),
            'total_draws'   => (int)($statsRow['total_draws'] ?? 0),
        ];

        $this->render('account', [
            'user' => $user,
            'stats' => $stats
        ]);
    }
}
