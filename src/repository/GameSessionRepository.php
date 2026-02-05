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
            INSERT INTO game_session_participants (session_id, user_id)
            VALUES (:session_id, :user_id)
            ON CONFLICT DO NOTHING
        ');
        $stmt->execute([
            ':session_id' => $uuid,
            ':user_id' => $userId
        ]);
    }

    public function getParticipants(string $sessionId): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT u.id, u.nickname, u.avatar_url
            FROM game_session_participants p
            JOIN users u ON u.id = p.user_id
            WHERE p.session_id = :session_id
            ORDER BY p.joined_at ASC
        ');
        $stmt->execute([':session_id' => $sessionId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

}
