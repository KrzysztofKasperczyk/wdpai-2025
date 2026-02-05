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

    /**
     * GET /api/session/latest?session_id=UUID
     */
    public function latest(): void
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

        $event = $this->eventRepo->getLatestEvent($sessionId);
        $this->json(['event' => $event], 200);
    }

    /**
     * POST /api/coin-flip/flip { session_id }
     */
    public function coinFlip(): void
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
        if ($session['game_type'] !== 'coin_flip') {
            $this->json(['error' => 'wrong game type for this endpoint'], 400);
        }

        $this->requireHost($session);
        $result = (random_int(0, 1) === 0) ? 'heads' : 'tails';

        $payload = [
            'result' => $result,
            'by_user_id' => (int)$_SESSION['user_id']
        ];

        $eventId = $this->eventRepo->createEvent($sessionId, 'coin_flip', $payload);

        $this->json([
            'ok' => true,
            'event_id' => $eventId,
            'result' => $result
        ], 200);
    }

    /**
     * POST /api/dice/roll { session_id, sides? }
     */
    public function diceRoll(): void
    {
        $this->requireAuth();

        if (!$this->isPost()) {
            $this->json(['error' => 'Method Not Allowed'], 405);
        }

        $data = $this->getJsonBody();
        $sessionId = $data['session_id'] ?? '';
        $sides = (int)($data['sides'] ?? 6);
        if ($sides < 2 || $sides > 1000) $sides = 6;

        if ($sessionId === '') {
            $this->json(['error' => 'session_id is required'], 400);
        }

        $session = $this->sessionRepo->findById($sessionId);
        if (!$session) {
            $this->json(['error' => 'session not found'], 404);
        }
        if ($session['game_type'] !== 'roll_dice') {
            $this->json(['error' => 'wrong game type for this endpoint'], 400);
        }

        $this->requireHost($session);
        $result = random_int(1, $sides);

        $payload = [
            'result' => $result,
            'sides' => $sides,
            'by_user_id' => (int)$_SESSION['user_id']
        ];

        $eventId = $this->eventRepo->createEvent($sessionId, 'roll_dice', $payload);

        $this->json(['ok' => true, 'event_id' => $eventId, 'result' => $result, 'sides' => $sides], 200);
    }

        /**
     * GET /api/session/participants?session_id=UUID
     */
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

        $participants = $this->sessionRepo->getParticipants($sessionId);

        $this->json([
            'participants' => $participants
        ], 200);
    }

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

        $this->sessionRepo->touchParticipant($sessionId, (int)$_SESSION['user_id']);
        $this->json(['ok' => true], 200);
    }

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

        $this->sessionRepo->leaveSession($sessionId, (int)$_SESSION['user_id']);
        $this->json(['ok' => true], 200);
    }

    private function requireHost(array $session): void
    {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($uid <= 0) {
            $this->json(['error' => 'unauthorized'], 401);
        }

        if ((int)$session['created_by'] !== $uid) {
            $this->json(['error' => 'Only the host can perform this action'], 403);
        }
    }

}
