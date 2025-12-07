<?php
require_once 'repository.php';

class UserRepository extends Repository {
    public function getUsers(): ?array
    {
        $query = $this->database->connect()->prepare('
            SELECT * FROM users
        ');
        $query->execute();

        //ogarnac co to fetch_obj
        $users = $query->fetchAll(PDO::FETCH_ASSOC);
        
        return $users;
    }
}