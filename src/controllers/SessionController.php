<?php

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repository/GameSessionRepository.php';

class SessionController extends AppController
{
    // Singleton
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
     * GET /session/create?game=coin_flip
     * Tworzy nową sesję gry i przekierowuje do /session/{uuid}
     */
    public function create(): void
    {
        // Tylko dla zalogowanych
        $this->requireAuth();

        // Whitelist dozwolonych gier
        $game = $_GET['game'] ?? null;
        if (!in_array($game, ['coin_flip'], true)) {
            $this->redirect('/tools');
        }

        // Generuje UUID dla sesji
        $uuid = $this->generateUuid();

        // User tworzący sesję jest hostem
        $userId = (int)$_SESSION['user_id'];

        // Zapisz sesję w DB oraz dodaj hosta jako uczestnika
        $this->repository->create($uuid, $game, $userId);


        $this->redirect('/session/' . $uuid);
    }

    /**
     * GET /session/{uuid}
     * Renderuje stronę sesji (widok) i dołącza użytkownika jako uczestnika
     */
    public function view(string $uuid): void
    {
        $this->requireAuth();

        // Pobierz sesję po id
        $session = $this->repository->findById($uuid);
        if (!$session) {
            $this->render('404');
            return;
        }

        // Dodaj/odśwież uczestnika
        $this->repository->addParticipant($uuid, (int)$_SESSION['user_id']);

        // Jeśli to coin_flip, przypisz coin_choice przy wejściu
        $this->repository->ensureCoinChoiceOnJoin($uuid, (int)$_SESSION['user_id']);

        // Czy aktualny user jest hostem?
        $isHost = ((int)$session['created_by'] === (int)$_SESSION['user_id']);

        // Renderuj widok sesji z danymi sesji, linkiem do zaproszenia i informacją czy user jest hostem
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
