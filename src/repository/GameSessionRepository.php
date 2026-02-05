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

        // Host jako uczestnik (presence + status)
        $stmt = $db->prepare('
            INSERT INTO game_session_participants (session_id, user_id, last_seen, left_at, status)
            VALUES (:session_id, :user_id, CURRENT_TIMESTAMP, NULL, \'active\')
            ON CONFLICT (session_id, user_id)
            DO UPDATE SET last_seen = CURRENT_TIMESTAMP, left_at = NULL
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
            INSERT INTO game_session_participants (session_id, user_id, last_seen, left_at, status)
            VALUES (:session_id, :user_id, CURRENT_TIMESTAMP, NULL, \'active\')
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
            SELECT u.id, u.nickname, u.avatar_url,
                   p.coin_choice, p.status,
                   (u.id = s.created_by) AS is_host
            FROM game_session_participants p
            JOIN users u ON u.id = p.user_id
            JOIN game_sessions s ON s.id = p.session_id
            WHERE p.session_id = :session_id
              AND p.left_at IS NULL
              AND p.last_seen >= (CURRENT_TIMESTAMP - (:ttl || \' seconds\')::interval)
            ORDER BY u.id ASC
        ');
        $stmt->execute([
            ':session_id' => $sessionId,
            ':ttl' => (string)$ttlSeconds
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

    /**
     * Coin flip: ustaw coin_choice przy wejściu:
     * - host: losowo
     * - pierwszy gość: przeciwna wartość do hosta
     * - kolejni: losowo
     */
    public function ensureCoinChoiceOnJoin(string $sessionId, int $userId): void
    {
        $db = $this->database->connect();
        $db->beginTransaction();

        try {
            $s = $db->prepare('SELECT id, game_type, created_by FROM game_sessions WHERE id = :id FOR UPDATE');
            $s->execute([':id' => $sessionId]);
            $session = $s->fetch(PDO::FETCH_ASSOC);

            if (!$session || $session['game_type'] !== 'coin_flip') {
                $db->commit();
                return;
            }

            // zablokuj participant
            $p = $db->prepare('
                SELECT coin_choice
                FROM game_session_participants
                WHERE session_id = :sid AND user_id = :uid
                FOR UPDATE
            ');
            $p->execute([':sid' => $sessionId, ':uid' => $userId]);
            $row = $p->fetch(PDO::FETCH_ASSOC);

            if (!$row) { $db->commit(); return; }
            if (!empty($row['coin_choice'])) { $db->commit(); return; }

            $hostId = (int)$session['created_by'];
            $isHost = ($userId === $hostId);

            // host coin_choice
            $hostStmt = $db->prepare('
                SELECT coin_choice
                FROM game_session_participants
                WHERE session_id = :sid AND user_id = :hid
                FOR UPDATE
            ');
            $hostStmt->execute([':sid' => $sessionId, ':hid' => $hostId]);
            $host = $hostStmt->fetch(PDO::FETCH_ASSOC);
            $hostChoice = $host ? ($host['coin_choice'] ?? null) : null;

            // ilu uczestników jest w sesji i nie wyszli (bez TTL)
            $cnt = $db->prepare('
                SELECT COUNT(*)::int AS cnt
                FROM game_session_participants
                WHERE session_id = :sid AND left_at IS NULL
            ');
            $cnt->execute([':sid' => $sessionId]);
            $total = (int)($cnt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

            $choice = null;

            if ($isHost) {
                $choice = (random_int(0, 1) === 0) ? 'heads' : 'tails';
            } else {
                // jeśli host jeszcze nie ma -> ustaw
                if (!$hostChoice) {
                    $hostChoice = (random_int(0, 1) === 0) ? 'heads' : 'tails';
                    $updHost = $db->prepare('
                        UPDATE game_session_participants
                        SET coin_choice = :c, status = \'active\'
                        WHERE session_id = :sid AND user_id = :hid
                    ');
                    $updHost->execute([':c' => $hostChoice, ':sid' => $sessionId, ':hid' => $hostId]);
                }

                // pierwszy gość po hoście: total == 2 (host + on)
                if ($total === 2) {
                    $choice = ($hostChoice === 'heads') ? 'tails' : 'heads';
                } else {
                    $choice = (random_int(0, 1) === 0) ? 'heads' : 'tails';
                }
            }

            $upd = $db->prepare('
                UPDATE game_session_participants
                SET coin_choice = :c, status = \'active\'
                WHERE session_id = :sid AND user_id = :uid
            ');
            $upd->execute([':c' => $choice, ':sid' => $sessionId, ':uid' => $userId]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Coin flip: przeprowadza rundę:
     * - eliminuje uczestników z coin_choice != result
     * - jeśli zostaje 1 -> winner
     * - jeśli zostaje >=2 -> nadaje nowe coin_choice (min. 2 różne)
     * + STATS:
     * - eliminated: total_draws +1
     * - winner: wins +1 oraz total_draws +1
     */
    public function processCoinFlipRound(string $sessionId, string $result): array
    {
        $db = $this->database->connect();
        $db->beginTransaction();

        try {
            $s = $db->prepare('SELECT id, game_type FROM game_sessions WHERE id = :id FOR UPDATE');
            $s->execute([':id' => $sessionId]);
            $session = $s->fetch(PDO::FETCH_ASSOC);

            if (!$session || $session['game_type'] !== 'coin_flip') {
                $db->commit();
                return ['winner_id' => null];
            }

            // aktywni + online uczestnicy (blokujemy wiersze)
            $p = $db->prepare('
                SELECT user_id, coin_choice
                FROM game_session_participants
                WHERE session_id = :sid
                  AND left_at IS NULL
                  AND status = \'active\'
                FOR UPDATE
            ');
            $p->execute([':sid' => $sessionId]);
            $active = $p->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // kto zostaje, kto odpada
            $remaining = [];
            $eliminatedIds = [];

            foreach ($active as $row) {
                $uid = (int)$row['user_id'];
                $choice = (string)($row['coin_choice'] ?? '');

                if ($choice === $result) {
                    $remaining[] = $uid;
                } else {
                    $eliminatedIds[] = $uid;
                }
            }

            // eliminacje w DB
            if (!empty($eliminatedIds)) {
                $elim = $db->prepare('
                    UPDATE game_session_participants
                    SET status = \'eliminated\', eliminated_at = CURRENT_TIMESTAMP
                    WHERE session_id = :sid
                      AND left_at IS NULL
                      AND status = \'active\'
                      AND coin_choice <> :res
                ');
                $elim->execute([':sid' => $sessionId, ':res' => $result]);

                // ---- STATS: eliminated -> total_draws + 1 ----
                foreach ($eliminatedIds as $uid) {
                    $st = $db->prepare('
                        INSERT INTO user_stats (user_id, total_draws, wins, losses, draws)
                        VALUES (:uid, 1, 0, 0, 0)
                        ON CONFLICT (user_id)
                        DO UPDATE SET total_draws = user_stats.total_draws + 1,
                                      last_activity = CURRENT_TIMESTAMP
                    ');
                    $st->execute([':uid' => (int)$uid]);
                }
            }

            // winner?
            if (count($remaining) === 1) {
                $winnerId = (int)$remaining[0];

                $w = $db->prepare('
                    UPDATE game_session_participants
                    SET status = \'winner\'
                    WHERE session_id = :sid AND user_id = :uid
                ');
                $w->execute([':sid' => $sessionId, ':uid' => $winnerId]);

                // ---- STATS: winner -> wins +1 AND total_draws +1 ----
                $st = $db->prepare('
                    INSERT INTO user_stats (user_id, total_draws, wins, losses, draws)
                    VALUES (:uid, 1, 1, 0, 0)
                    ON CONFLICT (user_id)
                    DO UPDATE SET total_draws = user_stats.total_draws + 1,
                                  wins = user_stats.wins + 1,
                                  last_activity = CURRENT_TIMESTAMP
                ');
                $st->execute([':uid' => $winnerId]);

                $db->commit();
                return ['winner_id' => $winnerId];
            }

            // reroll assignments dla pozostałych (min 2 różne jeśli >=2)
            if (count($remaining) >= 2) {
                shuffle($remaining);

                $assign = [];
                $assign[$remaining[0]] = 'heads';
                $assign[$remaining[1]] = 'tails';

                for ($i = 2; $i < count($remaining); $i++) {
                    $assign[$remaining[$i]] = (random_int(0, 1) === 0) ? 'heads' : 'tails';
                }

                foreach ($assign as $uid => $choice) {
                    $u = $db->prepare('
                        UPDATE game_session_participants
                        SET coin_choice = :c, round = round + 1
                        WHERE session_id = :sid
                          AND user_id = :uid
                          AND status = \'active\'
                          AND left_at IS NULL
                    ');
                    $u->execute([':c' => $choice, ':sid' => $sessionId, ':uid' => $uid]);
                }
            }

            $db->commit();
            return ['winner_id' => null];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
