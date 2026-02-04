<?php

require_once __DIR__ . '/repository.php';

class GameEventRepository extends Repository
{
    public function createEvent(string $sessionId, string $eventType, array $payload): int
    {
        $stmt = $this->database->connect()->prepare('
            INSERT INTO game_session_events (session_id, event_type, payload)
            VALUES (:session_id, :event_type, :payload::jsonb)
            RETURNING id
        ');

        $payloadJson = json_encode($payload);

        $stmt->execute([
            ':session_id' => $sessionId,
            ':event_type' => $eventType,
            ':payload'    => $payloadJson
        ]);

        return (int)$stmt->fetchColumn();
    }

    public function getLatestEvent(string $sessionId): ?array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT id, session_id, event_type, payload, created_at
            FROM game_session_events
            WHERE session_id = :session_id
            ORDER BY id DESC
            LIMIT 1
        ');

        $stmt->execute([':session_id' => $sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
