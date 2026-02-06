<?php

// klasa bazowa dla kontrolerów
abstract class AppController
{
    // Sprawdza czy request jest typu GET
    protected function isGet(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }

    // Sprawdza czy request jest typu POST
    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    // Wymaganie zalogowania
    protected function requireAuth(): void
    {
            // Jeśli w sesji znajduje się user_id, to użytkownik jest zalogowany
            if (isset($_SESSION['user_id'])) {
            return;
        }

        // Jeśli nie jest zalogowany, zapisz aktualny URL, żeby po zalogowaniu przekierować użytkownika
        // Fallback to /tools
        $uri = $_SERVER['REQUEST_URI'] ?? '/tools';

        // Nie zapisuj przekierowań dla stron logowania/rejestracji/wylogowania
        // Aby uniknąć pętli przekierowań np. /login -> /login -> /login ...
        $isAuthPage =
            str_starts_with($uri, '/login') ||
            str_starts_with($uri, '/register') ||
            str_starts_with($uri, '/logout');

        // Jeżeli przekierowanie jest ok, zapisz je w sesji, żeby po zalogowaniu przekierować użytkownika
        if (!$isAuthPage) {
            $_SESSION['redirect_after_login'] = $uri;
        }

        // Przerzuć na logowanie
        $this->redirect('/login');
    }

    // Renderuje widok z przekazaniem zmiennych
    protected function render(?string $template = null, array $variables = []): void
    {

        $viewsPath = __DIR__ . '/../../public/views/';

        // Docelowa ścieżka do template'u np. public/views/login.html
        $templatePath = $viewsPath . $template . '.html';

        // Ścieżka do 404, gdy template nie istnieje
        $template404 = $viewsPath . '404.html';

        // Zamienia tablicę $variables na zmienne w aktualnym zakresie:
        // ['username' => 'Jan'] -> w widoku dostępne jako $username
        // EXTR_SKIP = nie nadpisuj istniejących zmiennych o tej samej nazwie
        extract($variables, EXTR_SKIP);

        // Buforowanie outputu, żeby można było użyć funkcji header() do ustawienia nagłówków (np. do przekierowania) nawet po renderowaniu widoku
        ob_start();

        // Jeśli template istnieje, dołącz go, jak nie to 404
        if ($template && file_exists($templatePath)) {
            // Include pozwala na używanie kodu PHP w plikach .html bo include idzie przez interpreter PHP
            include $templatePath;
        } else {
            include $template404;
        }

        // Pobierz zawartość bufora i wyczyść go
        $output = ob_get_clean();

        // Wyślij do przeglądarki
        echo $output;
    }

    // Szybkie przekierowanie
    protected function redirect(string $path): void
    {
        // Ustawia nagłówek Location, który mówi przeglądarce, żeby przejść pod inny URL
        header("Location: {$path}");
        exit();
    }

    // Szybkie zwracanie JSON'a, np. do API
    protected function json(array $data, int $status = 200): void
    {
        // Ustawia kod statusu HTTP
        http_response_code($status);
        // Ustawia nagłówek Content-Type, że odpowiedź to JSON
        header('Content-Type: application/json');
        // Zamienia tablicę $data na JSON i wysyła do przeglądarki
        echo json_encode($data);
        exit();
    }

    // Pobiera dane JSON z body requestu
    // Przydaje się przy fetch POST/PUT
    protected function getJsonBody(): array
    {
        // Surowy body request (np. JSON wysłany przez fetch)
        $raw = file_get_contents('php://input');

        // true -> tablica asocjacyjna zamiast obiektów stdClass
        $data = json_decode($raw, true);

        // Jeśli dekodowanie nie dało tablicy, zwróć pustą tablicę
        return is_array($data) ? $data : [];
    }
}
