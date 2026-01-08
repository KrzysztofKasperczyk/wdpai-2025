<?php
require_once 'AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';

class AccountController extends AppController
{
    private static ?self $instance = null;
    private UserRepository $userRepository;

    private function __construct() {
        $this->userRepository = new UserRepository();
    }
    private function __clone() {}
    public function __wakeup() { throw new \Exception("Cannot unserialize singleton"); }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function index()
    {
        $this->requireAuth();

        $user = $this->userRepository->getUserById((int)$_SESSION['user_id']);
        // na razie mock stats
        $stats = [
            'arguments_won' => 17,
            'total_draws' => 42
        ];

        return $this->render('account', [
            'user' => $user,
            'stats' => $stats
        ]);
    }
}
