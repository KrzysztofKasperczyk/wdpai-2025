<?php

require_once __DIR__ . '/repository.php';

class GameEventRepository extends Repository
{
    // Tworzy nowe zdarzenie dla danej sesji i zwraca jego ID
    public function createEvent(string $sessionId, string $eventType, array $payload): int
    {

        // Zapis zdarzenia do bazy danych, payload jest przechowywany jako JSONB
        $stmt = $this->database->connect()->prepare('
            INSERT INTO game_session_events (session_id, event_type, payload)
            VALUES (:session_id, :event_type, :payload::jsonb)
            RETURNING id
        ');

        // Konwersja payload na JSON przed zapisaniem
        $payloadJson = json_encode($payload);

        // Wykonanie zapytania z bindowaniem wartości
        $stmt->execute([
            ':session_id' => $sessionId,
            ':event_type' => $eventType,
            ':payload'    => $payloadJson
        ]);

        // Postgres zwraca id nowego rekordu dzięki RETURNING id
        return (int)$stmt->fetchColumn();
    }


    // Pobiera najnowsze zdarzenie dla danej sesji, zwraca tablicę z danymi lub null jeśli brak zdarzeń
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
