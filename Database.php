<?php
//read variables from .env instead
require_once "config.php";

//zrobi singelton
//przerzucic do src/services


class Database {
    private $username;
    private $password;
    private $host;
    private $database;

    public function __construct()
    {
        $this->username = USERNAME;
        $this->password = PASSWORD;
        $this->host = HOST;
        $this->database = DATABASE;
    }

    public function connect()
    {
        try {
            $conn = new PDO(
                "pgsql:host=$this->host;port=5432;dbname=$this->database",
                $this->username,
                $this->password,
                ["sslmode"  => "prefer"]
            );

            // set the PDO error mode to exception
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $conn;
        }
        //zamiast die -> zwróć strone z błędem
        //napisać metode disconnect, ustawic jakas zmienna ktora bedzie sie ustawiac na null
        catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
}