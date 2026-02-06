<?php
# Uruchomienie sesji, można korzystać z $_SESSION do przechowywania informacji o zalogowanym użytkowniku
session_start();

require_once 'Routing.php';

# Pobranie ścieżki z URL
$path = trim($_SERVER['REQUEST_URI'], '/');

# Usunięcie query string, np. z /login?x=1 zostanie tylko /login
$path = parse_url($path, PHP_URL_PATH);

# Uruchomienie routingu, który przekieruje do odpowiedniego kontrolera i metody na podstawie ścieżki
Routing::run($path);