<?php

abstract class AppController
{
    /**
     * Sprawdza czy request jest typu GET
     */
    protected function isGet(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }

    /**
     * Sprawdza czy request jest typu POST
     */
    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Wymaga zalogowanego użytkownika
     * Jeśli nie jest zalogowany -> redirect do /login
     */
    protected function requireAuth(): void
    {
            if (isset($_SESSION['user_id'])) {
            return;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/tools';

        // Nie zapisuj redirectu dla stron logowania/rejestracji/wylogowania
        // (to najczęstsza przyczyna pętli przekierowań)
        $isAuthPage =
            str_starts_with($uri, '/login') ||
            str_starts_with($uri, '/register') ||
            str_starts_with($uri, '/logout');

        if (!$isAuthPage) {
            $_SESSION['redirect_after_login'] = $uri;
        }

        $this->redirect('/login');
    }

    /**
     * Renderuje widok z przekazaniem zmiennych
     *
     * @param string|null $template  nazwa pliku bez .html
     * @param array       $variables zmienne dostępne w widoku
     */
    protected function render(?string $template = null, array $variables = []): void
    {
        $viewsPath = __DIR__ . '/../../public/views/';
        $templatePath = $viewsPath . $template . '.html';
        $template404 = $viewsPath . '404.html';

        // udostępniamy zmienne w widoku
        extract($variables, EXTR_SKIP);

        ob_start();

        if ($template && file_exists($templatePath)) {
            include $templatePath;
        } else {
            include $template404;
        }

        $output = ob_get_clean();
        echo $output;
    }

    /**
     * Szybkie przekierowanie
     */
    protected function redirect(string $path): void
    {
        header("Location: {$path}");
        exit();
    }

    /**
     * Render JSON (do API)
     */
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }

    /**
     * Pobranie danych JSON z body requestu
     */
    protected function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }
}
