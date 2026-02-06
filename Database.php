<?php

require_once "config.php";

// Prosta klasa do obsługi połączenia z bazą danych
// Nie wykonuje zapytań -> Repozytoria
class Database {

    // Dane do połączenia, pobierane z config.php
    private $username;
    private $password;
    private $host;
    private $database;

    // Konstruktor pobiera dane ze stałych i zapisuje je w obiekcie
    public function __construct()
    {
        $this->username = USERNAME;
        $this->password = PASSWORD;
        $this->host = HOST;
        $this->database = DATABASE;
    }

    // Tworzy i zwraca połączenie PDO do PostgreSQL 
    public function connect()
    {
        try {
            $conn = new PDO(
                "pgsql:host=$this->host;port=5432;dbname=$this->database",
                $this->username,
                $this->password,
                ["sslmode"  => "prefer"]
            );

            // Ustawia tryb raportowania błędów na wyjątki
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Zwraca obiekt połączenia, który będzie używany w repozytoriach do wykonywania zapytań SQL
            return $conn;
        }
        

        // Jeśli połączenie się nie powiedzie, wyświetla komunikat o błędzie
        catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
}