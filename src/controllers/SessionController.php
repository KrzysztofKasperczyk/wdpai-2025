<?php

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repository/GameSessionRepository.php';

class SessionController extends AppController
{
    private static ?self $instance = null;
    private GameSessionRepository $repository;

    private function __construct()
    {
        $this->repository = new GameSessionRepository();
    }

    private function __clone() {}
    public function __wakeup() { throw new \Exception("Cannot unserialize singleton"); }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * /session/create?game=coin_flip
     */
    public function create(): void
    {
        $this->requireAuth();

        $game = $_GET['game'] ?? null;
        if (!in_array($game, ['coin_flip', 'roll_dice'], true)) {
            $this->redirect('/tools');
        }

        $uuid = $this->generateUuid();
        $userId = (int)$_SESSION['user_id'];

        $this->repository->create($uuid, $game, $userId);

        $this->redirect('/session/' . $uuid);
    }

    /**
     * /session/{uuid}
     */
    public function view(string $uuid): void
    {
        $this->requireAuth();

        $session = $this->repository->findById($uuid);
        if (!$session) {
            $this->render('404');
            return;
        }

        $this->repository->addParticipant($uuid, (int)$_SESSION['user_id']);
        $this->repository->ensureCoinChoiceOnJoin($uuid, (int)$_SESSION['user_id']);


        $isHost = ((int)$session['created_by'] === (int)$_SESSION['user_id']);
        $this->render('session', [
            'session' => $session,
            'inviteLink' => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/session/' . $uuid,
            'currentUserId' => (int)$_SESSION['user_id'],
            'isHost' => $isHost
        ]);
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );
    }
}
