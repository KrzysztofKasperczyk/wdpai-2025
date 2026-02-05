<?php

require_once __DIR__ . '/repository.php';

class UserRepository extends Repository
{
    public function getUserById(int $id): ?array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT id, nickname, email, avatar_url, created_at
            FROM users
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = null;

        return $user ?: null;
    }

    public function getUserByEmail(string $email): ?array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT *
            FROM users
            WHERE email = :email
            LIMIT 1
        ');
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = null;

        return $user ?: null;
    }

    public function getUserByNickname(string $nickname): ?array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT *
            FROM users
            WHERE nickname = :nickname
            LIMIT 1
        ');
        $stmt->bindParam(':nickname', $nickname, PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = null;

        return $user ?: null;
    }

    /**
     * Tworzy użytkownika i zwraca jego ID
     */
    public function createUser(string $nickname, string $email, string $password): int
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->database->connect()->prepare('
            INSERT INTO users (nickname, email, password)
            VALUES (:nickname, :email, :password)
            RETURNING id
        ');
        $stmt->bindParam(':nickname', $nickname, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':password', $hash, PDO::PARAM_STR);

        $stmt->execute();
        $id = (int)$stmt->fetchColumn();
        $stmt = null;

        // (opcjonalnie) od razu tworzymy rekord stats
        $this->ensureUserStatsRow($id);

        return $id;
    }

    /**
     * Tworzy pusty rekord user_stats, jeśli go nie ma
     */
    private function ensureUserStatsRow(int $userId): void
    {
        $stmt = $this->database->connect()->prepare('
            INSERT INTO user_stats (user_id)
            VALUES (:user_id)
            ON CONFLICT (user_id) DO NOTHING
        ');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $stmt = null;
    }

    public function getUsers(): array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT id, nickname, email, avatar_url, created_at
            FROM users
            ORDER BY id DESC
        ');
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = null;

        return $users ?: [];
    }

    public function getStatsByUserId(int $userId): array
    {
        $q = $this->database->connect()->prepare('
            SELECT total_draws, wins
            FROM user_stats
            WHERE user_id = :uid
        ');
        $q->execute([':uid' => $userId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);

        return $row ?: ['total_draws' => 0, 'wins' => 0];
    }

}
