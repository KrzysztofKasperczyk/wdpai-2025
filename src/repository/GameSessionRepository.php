<?php

require_once __DIR__ . '/repository.php';

class GameSessionRepository extends Repository
{
    public function create(string $uuid, string $gameType, int $userId): void
    {
        $db = $this->database->connect();

        $stmt = $db->prepare('
            INSERT INTO game_sessions (id, game_type, created_by)
            VALUES (:id, :game_type, :created_by)
        ');
        $stmt->execute([
            ':id' => $uuid,
            ':game_type' => $gameType,
            ':created_by' => $userId
        ]);

        $stmt = $db->prepare('
            INSERT INTO game_session_participants (session_id, user_id)
            VALUES (:session_id, :user_id)
        ');
        $stmt->execute([
            ':session_id' => $uuid,
            ':user_id' => $userId
        ]);
    }

    public function findById(string $uuid): ?array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT * FROM game_sessions WHERE id = :id
        ');
        $stmt->execute([':id' => $uuid]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        return $session ?: null;
    }

    public function addParticipant(string $uuid, int $userId): void
    {
        $stmt = $this->database->connect()->prepare('
            INSERT INTO game_session_participants (session_id, user_id, last_seen, left_at)
            VALUES (:session_id, :user_id, CURRENT_TIMESTAMP, NULL)
            ON CONFLICT (session_id, user_id)
            DO UPDATE SET last_seen = CURRENT_TIMESTAMP, left_at = NULL
        ');
        $stmt->execute([
            ':session_id' => $uuid,
            ':user_id' => $userId
        ]);
    }

    public function getParticipants(string $sessionId, int $ttlSeconds = 15): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT u.id, u.nickname, u.avatar_url
            FROM game_session_participants p
            JOIN users u ON u.id = p.user_id
            WHERE p.session_id = :session_id
            AND p.left_at IS NULL
            AND p.last_seen >= (CURRENT_TIMESTAMP - (:ttl || \' seconds\')::interval)
            ORDER BY u.id ASC
        ');
        $stmt->execute([
            ':session_id' => $sessionId,
            ':ttl' => (string)$ttlSeconds
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }


    public function touchParticipant(string $sessionId, int $userId): void
{
    $stmt = $this->database->connect()->prepare('
        UPDATE game_session_participants
        SET last_seen = CURRENT_TIMESTAMP, left_at = NULL
        WHERE session_id = :session_id AND user_id = :user_id
    ');
    $stmt->execute([
        ':session_id' => $sessionId,
        ':user_id' => $userId
    ]);
}

public function leaveSession(string $sessionId, int $userId): void
{
    $stmt = $this->database->connect()->prepare('
        UPDATE game_session_participants
        SET left_at = CURRENT_TIMESTAMP
        WHERE session_id = :session_id AND user_id = :user_id
    ');
    $stmt->execute([
        ':session_id' => $sessionId,
        ':user_id' => $userId
    ]);
}


public function leaveAllSessions(int $userId): void
{
    $stmt = $this->database->connect()->prepare('
        UPDATE game_session_participants
        SET left_at = CURRENT_TIMESTAMP
        WHERE user_id = :user_id AND left_at IS NULL
    ');
    $stmt->execute([':user_id' => $userId]);
}


}
