<?php
require_once 'repository.php';

class UserRepository extends Repository {

    public function getUsers(): array
    {
        $query = $this->database->connect()->prepare('
            SELECT id, nickname, email, avatar_url, created_at
            FROM users
        ');
        $query->execute();

        $users = $query->fetchAll(PDO::FETCH_ASSOC);
        $query = null;
        return $users ?: [];
    }
    
    public function getUserByEmail(string $email): ?array
    {
        $query = $this->database->connect()->prepare('
            SELECT * FROM users WHERE email = :email
        ');
        $query->bindParam(':email', $email);
        $query->execute();
        $user = $query->fetch(PDO::FETCH_ASSOC);
        $query = null;
        return $user ?: null;
    }

    public function getUserByNickname(string $nickname): ?array
    {
        $query = $this->database->connect()->prepare('
            SELECT * FROM users WHERE nickname = :nickname
        ');
        $query->bindParam(':nickname', $nickname);
        $query->execute();
        $user = $query->fetch(PDO::FETCH_ASSOC);
        $query = null;
        return $user ?: null;
    }

    public function createUser(
        string $nickname, 
        string $email, 
        string $password
    ): void {
        $query = $this->database->connect()->prepare('
            INSERT INTO users (nickname, email, password)
            VALUES (:nickname, :email, :password)
        ');
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $query->bindParam(':nickname', $nickname);
        $query->bindParam(':email', $email);
        $query->bindParam(':password', $hash);
        $query->execute();
        $query = null;
    }
}
