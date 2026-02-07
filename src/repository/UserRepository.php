<?php

require_once __DIR__ . '/repository.php';

class UserRepository extends Repository
{
    private static ?self $instance = null;

    // Singleton
    private function __construct()
    {
        parent::__construct();
    }

    // blokada klonowania singletona
    private function __clone() {}


    // blokada unserializowania singletona
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }

    // Metoda do pobrania instancji singletona
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }


    // Metoda do pobrania użytkownika po ID, zwraca tablicę z danymi lub null jeśli nie znaleziono
    public function getUserById(int $id): ?array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT id, nickname, email, avatar_url, created_at
            FROM users
            WHERE id = :id
            LIMIT 1
        ');

        // Bind parametru ID i wykonaj zapytanie
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        // Pobierz wynik jako tablicę asocjacyjną
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = null;

        // Zwróć dane użytkownika lub null jeśli nie znaleziono
        return $user ?: null;
    }


    // Metoda do pobrania użytkownika po emailu, zwraca tablicę z danymi lub null jeśli nie znaleziono
    public function getUserByEmail(string $email): ?array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT *
            FROM users
            WHERE email = :email
            LIMIT 1
        ');

        // Bind parametru email i wykonaj zapytanie
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        // Pobierz wynik jako tablicę asocjacyjną
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = null;

        // Zwróć dane użytkownika lub null jeśli nie znaleziono
        return $user ?: null;
    }

    // Metoda do pobrania użytkownika po nickname, zwraca tablicę z danymi lub null jeśli nie znaleziono
    public function getUserByNickname(string $nickname): ?array
    {
        $stmt = $this->database->connect()->prepare('
            SELECT *
            FROM users
            WHERE nickname = :nickname
            LIMIT 1
        ');

        // Bind parametru nickname i wykonaj zapytanie
        $stmt->bindParam(':nickname', $nickname, PDO::PARAM_STR);
        $stmt->execute();

        // Pobierz wynik jako tablicę asocjacyjną
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = null;

        // Zwróć dane użytkownika lub null jeśli nie znaleziono
        return $user ?: null;
    }

    // Metoda do tworzenia nowego użytkownika, zwraca ID
    public function createUser(string $nickname, string $email, string $password): int
    {
        // Hashowanie hasła przed zapisaniem do bazy
        $hash = password_hash($password, PASSWORD_BCRYPT);

        // Przygotowanie zapytania SQL do wstawienia nowego użytkownika
        $stmt = $this->database->connect()->prepare('
            INSERT INTO users (nickname, email, password)
            VALUES (:nickname, :email, :password)
            RETURNING id
        ');

        // Bind parametrów i wykonaj zapytanie
        $stmt->bindParam(':nickname', $nickname, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':password', $hash, PDO::PARAM_STR);

        $stmt->execute();

        // fetchColumn pobiera pierwszą kolumnę pierwszego wiersza -> tu: id
        $id = (int)$stmt->fetchColumn();
        $stmt = null;

        // Tworzy pusty rekord statystyk (jeśli jeszcze nie istnieje)
        $this->ensureUserStatsRow($id);

        return $id;
    }

    
    //Tworzy pusty rekord user_stats, jeśli go nie ma
    private function ensureUserStatsRow(int $userId): void
    {
        $stmt = $this->database->connect()->prepare('
            INSERT INTO user_stats (user_id)
            VALUES (:user_id)
            ON CONFLICT (user_id) DO NOTHING
        ');

        // Bind parametru user_id i wykonaj zapytanie
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

        // fetchAll -> tablica wierszy
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = null;

        // Zwróć tablicę użytkowników lub pustą tablicę jeśli brak wyników
        return $users ?: [];
    }

    public function getStatsByUserId(int $userId): array
    {
        $q = $this->database->connect()->prepare('
            SELECT total_draws, wins
            FROM user_stats
            WHERE user_id = :uid
            LIMIT 1
        ');

        // Bind parametru user_id i wykonaj zapytanie
        $q->execute([':uid' => $userId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        $q = null;

        // Zwróć dane statystyk lub domyślne wartości jeśli brak rekordu
        return $row ?: ['total_draws' => 0, 'wins' => 0];
    }
}
