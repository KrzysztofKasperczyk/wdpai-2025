<?php
// Prosta klasa bazowa dla repozytoriów, która zapewnia dostęp do bazy danych
require_once __DIR__.'/../../Database.php';

// Klasa bazowa dla wszystkich repozytoriów
class Repository {

    // Chroniona właściwość $database, dostępna dla klas dziedziczących
    protected $database;

    // Konstruktor
    public function __construct()
    {
        // Każde repozytorium dostaje własny obiekt Database
        // który odpowiada za połączenie i zapytania SQL
        $this->database = new Database();
    }
}