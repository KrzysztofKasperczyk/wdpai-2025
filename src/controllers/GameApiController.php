<?php

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repository/GameSessionRepository.php';
require_once __DIR__ . '/../repository/GameEventRepository.php';

class GameApiController extends AppController
{
    private static ?self $instance = null;

    private GameSessionRepository $sessionRepo;
    private GameEventRepository $eventRepo;

    private function __construct()
    {
        $this->sessionRepo = new GameSessionRepository();
        $this->eventRepo = new GameEventRepository();
    }

    private function __clone() {}
    public function __wakeup() { throw new \Exception("Cannot unserialize singleton"); }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    // Zwraca najnowsze zdarzenie dla danej sesji (lub null jeśli brak zdarzeń)
    public function latest(): void
    {
        // Wymaga zalogowanego użytkownika
        $this->requireAuth();

        // Pobierz session_id z query stringa
        $sessionId = $_GET['session_id'] ?? '';
        if ($sessionId === '') {
            $this->json(['error' => 'session_id is required'], 400);
        }

        // Sprawdź czy sesja istnieje
        $session = $this->sessionRepo->findById($sessionId);
        if (!$session) {
            $this->json(['error' => 'session not found'], 404);
        }

        // Pobierz najnowsze zdarzenie dla tej sesji
        $event = $this->eventRepo->getLatestEvent($sessionId);
        $this->json(['event' => $event], 200);
    }

    // Endpoint do wykonania rundy w grze "coin flip"
    public function coinFlip(): void
    {
        $this->requireAuth();

        // Sprawdź czy metoda to POST
        if (!$this->isPost()) {
            $this->json(['error' => 'Method Not Allowed'], 405);
        }

        // Pobierz dane z body (JSON)
        $data = $this->getJsonBody();
        $sessionId = $data['session_id'] ?? '';

        if ($sessionId === '') {
            $this->json(['error' => 'session_id is required'], 400);
        }

        // Sesja musi istnieć i być typu "coin_flip"
        $session = $this->sessionRepo->findById($sessionId);
        if (!$session) {
            $this->json(['error' => 'session not found'], 404);
        }
        if ($session['game_type'] !== 'coin_flip') {
            $this->json(['error' => 'wrong game type for this endpoint'], 400);
        }

        // Tylko host może wykonać rundę
        $this->requireHost($session);

        // losowo wybierz heads lub tails
        $result = (random_int(0, 1) === 0) ? 'heads' : 'tails';


        // Przetwórz rundę w DB (np. zapis wyniku, wyliczenie zwycięzcy, statystyki)
        $round = $this->sessionRepo->processCoinFlipRound($sessionId, $result);
        $winnerId = $round['winner_id'] ?? null;


        // Payload eventu - zapisujesz kto wykonał akcję + wynik
        $payload = [
        'result' => $result,
        'by_user_id' => (int)$_SESSION['user_id'],
        'winner_id' => $winnerId
        ];

        // Zapisz event
        $eventId = $this->eventRepo->createEvent($sessionId, 'coin_flip', $payload);


        // Odpowiedź JSON
        $this->json(['ok'=>true,'event_id'=>$eventId,'result'=>$result,'winner_id'=>$winnerId], 200);

    }
    
    // Endpoint do pobrania listy uczestników sesji
    public function participants(): void
    {
        
        $this->requireAuth();

        $sessionId = $_GET['session_id'] ?? '';
        if ($sessionId === '') {
            $this->json(['error' => 'session_id is required'], 400);
        }

        $session = $this->sessionRepo->findById($sessionId);
        if (!$session) {
            $this->json(['error' => 'session not found'], 404);
        }

        // Pobierz listę uczestników sesji
        $participants = $this->sessionRepo->getParticipants($sessionId);

        $this->json([
            'participants' => $participants
        ], 200);
    }

    // Służy jako keep-alive / odświeżanie obecności użytkownika w sesji.
    public function ping(): void
    {
        $this->requireAuth();

        if (!$this->isPost()) {
            $this->json(['error' => 'Method Not Allowed'], 405);
        }

        $data = $this->getJsonBody();
        $sessionId = $data['session_id'] ?? '';
        if ($sessionId === '') {
            $this->json(['error' => 'session_id is required'], 400);
        }

        $session = $this->sessionRepo->findById($sessionId);
        if (!$session) {
            $this->json(['error' => 'session not found'], 404);
        }

        // Aktualizuje timestamp/stan uczestnika w DB
        $this->sessionRepo->touchParticipant($sessionId, (int)$_SESSION['user_id']);
        $this->json(['ok' => true], 200);
    }


    // Endpoint do opuszczenia sesji przez uczestnika
    public function leave(): void
    {
        $this->requireAuth();

        if (!$this->isPost()) {
            $this->json(['error' => 'Method Not Allowed'], 405);
        }

        $data = $this->getJsonBody();
        $sessionId = $data['session_id'] ?? '';
        if ($sessionId === '') {
            $this->json(['error' => 'session_id is required'], 400);
        }

        // Usuwa użytkownika z sesji (DB)
        $this->sessionRepo->leaveSession($sessionId, (int)$_SESSION['user_id']);
        $this->json(['ok' => true], 200);
    }

    // Pomocnicza metoda do sprawdzenia czy aktualny użytkownik jest hostem sesji
    private function requireHost(array $session): void
    {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($uid <= 0) {
            $this->json(['error' => 'unauthorized'], 401);
        }
        // Jeśli user nie jest twórcą sesji -> 403 Forbidden
        if ((int)$session['created_by'] !== $uid) {
            $this->json(['error' => 'Only the host can perform this action'], 403);
        }
    }

}
