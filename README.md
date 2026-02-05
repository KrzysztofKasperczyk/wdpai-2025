# Opis aplikacji

FairPick to webowa aplikacja umożliwiająca uczciwe i przejrzyste podejmowanie decyzji losowych pomiędzy wieloma użytkownikami w czasie rzeczywistym.

Aplikacja została zaprojektowana do sytuacji, w których kilka osób musi podjąć wspólną decyzję, a losowanie ma być:

- jednoznaczne,
- widoczne dla wszystkich uczestników,
- odporne na manipulacje.

## Funkcjonalność użytkownika

Zarejestrowany użytkownik może:

- utworzyć sesję gry (coin flip),
- zaprosić innych użytkowników do sesji poprzez unikalny link,
- uczestniczyć w grze jako host lub obserwator,
- obserwować przebieg gry i eliminacje uczestników w czasie rzeczywistym,
- zobaczyć zwycięzcę po zakończeniu rozgrywki,
- przeglądać swoje statystyki (liczba rozegranych gier, liczba wygranych),
- zarządzać swoim kontem (logowanie, rejestracja, wylogowanie).

## Model rozgrywki

Każda gra odbywa się w ramach sesji.
Jedna osoba jest hostem i kontroluje przebieg gry.
Pozostali użytkownicy dołączają do sesji poprzez link.
Wyniki losowań są zapisywane i synchronizowane pomiędzy uczestnikami.

<br> 

---


# Architektura aplikacji

Aplikacja **FairPick** została zaprojektowana w oparciu o prostą architekturę **MVC (Model–View–Controller)** z ręcznie zaimplementowanym routingiem oraz wydzielonym API.

## Stack technologiczny
- **PHP 8** – backend
- **PostgreSQL** – baza danych
- **Nginx + PHP-FPM** – serwer aplikacji
- **JavaScript** – frontend
- **Docker** – środowisko uruchomieniowe

## Podział warstw

### Controllers (`src/controllers/`)
Obsługują żądania HTTP oraz API.  
Nie zawierają zapytań SQL ani logiki dostępu do danych.

**Przykład:**
```php
$this->render('account', ['stats' => $stats]);
```

### Repositories (`src/repository/`)
Odpowiadają za logikę biznesową oraz komunikację z bazą danych.  
Zawierają zapytania SQL i transakcje.

Przykład:
```php
$db->beginTransaction();
// logika gry i aktualizacja statystyk
db->commit();
```

### Views (`public/views/`)
Statyczne widoki HTML z prostymi wstawkami PHP.  
brak logiki biznesowej.

### Frontend (`public/scripts/`)
Obsługa interakcji użytkownika:
- logika gry,
- polling danych,
- obecność uczestników (heartbeat).

## Routing
Routing realizowany ręcznie w pliku `Routing.php`.  
mapsuje adres URL na odpowiedni kontroler i metodę.

**Przykład:**
```php
sessionController::getInstance()->view($sessionId);
```

## Komunikacja w czasie rzeczywistym
Aplikacja nie używa WebSocketów.  
zastosowano:
- HTTP polling,
- heartbeat użytkowników,
- TTL po stronie bazy danych.

<br>

---

# Backend — programowanie obiektowe

Backend aplikacji został napisany w sposób obiektowy (OOP). Logika biznesowa jest podzielona na klasy o jasno określonych odpowiedzialnościach, co ułatwia rozwój, testowanie i utrzymanie kodu.

## Główne elementy OOP
- **Kontrolery** – obsługa żądań HTTP i logiki aplikacji
- **Repozytoria** – dostęp do bazy danych
- **Dziedziczenie** – wspólna logika w klasach bazowych
- **Enkapsulacja** – logika ukryta w metodach klas

### Przykład: kontroler

**Plik:** `src/controllers/GameApiController.php`

```php
class GameApiController extends AppController
{
    private GameSessionRepository $sessionRepo;

    public function latest(): void
    {
        $this->requireAuth();
        $sessionId = $_GET['session_id'] ?? '';
        $session = $this->sessionRepo->findById($sessionId);
        $this->json(['session' => $session]);
    }
}
```

<br>

---

# Diagram ERD
<img width="707" height="618" alt="image" src="https://github.com/user-attachments/assets/5e127afc-e3d8-457b-8273-bcdd2dad4b44" />


### `users`
Przechowuje dane użytkowników aplikacji.
- **Najważniejsze pola:** `id`, `nickname`, `email`, `password`
- **Relacje:**
  - 1:N z `game_sessions` (użytkownik może utworzyć wiele sesji)
  - 1:N z `game_session_participants`
  - 1:N z `game_events`

### `game_sessions`
Reprezentuje pojedynczą sesję gry (np. coin flip).
- **Najważniejsze pola:** `id (UUID)`, `game_type`, `created_by`
- **Relacje:**
  - N:1 z `users` (`created_by`)
  - 1:N z `game_session_participants`
  - 1:N z `game_events`

### `game_session_participants`
Tabela łącząca użytkowników z sesjami gier (uczestnictwo).
- **Najważniejsze pola:**  
  `session_id`, `user_id`, `coin_choice`, `status`, `last_seen`, `left_at`
- **Relacje:**
  - N:1 z `users`
  - N:1 z `game_sessions`
- **Rola w systemie:**
  - przechowuje stan gracza w danej grze (aktywny, wyeliminowany, zwycięzca),
  - obsługuje presence (heartbeat, opuszczenie sesji).

### `game_events`
Zapisuje wszystkie zdarzenia w trakcie gry.
- **Najważniejsze pola:** `id`, `session_id`, `type`, `payload`, `created_at`
- **Relacje:**
  - N:1 z `game_sessions`
- **Rola w systemie:**
  - umożliwia synchronizację stanu gry pomiędzy uczestnikami,
  - backend + frontend korzystają z niej do odtwarzania wyników (polling).

### `user_stats` (jeśli występuje lub planowana)
Przechowuje statystyki użytkownika.
- **Najważniejsze pola:** `user_id`, `arguments_won`, `total_draws`
- **Relacje:**
  - 1:1 z `users`
- **Rola w systemie:**
  - zasilana po zakończeniu gry (winner / eliminacja),
  - wykorzystywana w sekcji Account.

<br>

---

# HTML

HTML odpowiada za **strukturę widoków aplikacji** oraz podstawowy układ
interfejsu użytkownika. Widoki są zapisane jako **statyczne pliki `.html`**
i nie zawierają logiki aplikacyjnej.

HTML definiuje:
- rozmieszczenie elementów na stronie,
- formularze (logowanie, rejestracja),
- kontenery dla danych ładowanych dynamicznie przez JavaScript.

Dane aplikacyjne są pobierane z backendu i wstawiane do HTML
po stronie klienta.

- `login.html` – formularz logowania użytkownika
- `register.html` – formularz rejestracji nowego konta
- `tools.html` – ekran wyboru gier (kafelki)
- `tool-coin-flip.html` – ekran startowy gry Coin Flip (utworzenie sesji)
- `session.html` – ekran aktywnej sesji gry (coin flip, participants)
- `account.html` – widok profilu użytkownika i statystyk
- `layout-topbar.html` – wspólny górny pasek nawigacyjny
- `404.html` – strona błędu dla nieistniejących tras

<br>

---

# PHP

PHP odpowiada za **backend aplikacji** – obsługę logiki biznesowej,
autoryzacji użytkowników, komunikację z bazą danych oraz udostępnianie API
dla frontendu.

Backend działa w architekturze zbliżonej do MVC:
- kontrolery obsługują żądania HTTP,
- repozytoria zarządzają dostępem do danych,
- routing mapuje adresy URL na odpowiednie akcje.

### Pliki główne
- `index.php` – punkt wejścia aplikacji, uruchamia routing
- `Routing.php` – mapowanie adresów URL na kontrolery i akcje

### Kontrolery
- `AppController.php` – klasa bazowa kontrolerów (auth, redirect, JSON)
- `SecurityController.php` – logowanie, rejestracja i wylogowanie
- `ToolsController.php` – obsługa widoku wyboru gier
- `SessionController.php` – tworzenie i wyświetlanie sesji gier
- `GameApiController.php` – API do obsługi gry i synchronizacji stanu
- `AccountController.php` – obsługa profilu użytkownika i statystyk

### Repozytoria
- `repository.php` – klasa bazowa repozytoriów (dostęp do DB)
- `UserRepository.php` – operacje na użytkownikach i statystykach
- `GameSessionRepository.php` – sesje gier i uczestnicy
- `GameEventRepository.php` – zdarzenia i przebieg gry

### Konfiguracja
- `Database.php` – połączenie z bazą danych (PDO)
- `config.php` – konfiguracja połączenia z bazą
  
### Przykład użycia
```php
public function participants(): void
{
    $this->requireAuth();

    $sessionId = $_GET['session_id'] ?? '';
    $participants = $this->sessionRepo->getParticipants($sessionId);

    $this->json(['participants' => $participants]);
}
```

<br>

---

# JavaScript

JavaScript odpowiada za **logikę po stronie klienta** oraz dynamiczne
zachowanie interfejsu użytkownika. Umożliwia komunikację z backendem,
aktualizację danych bez przeładowania strony oraz obsługę interakcji
użytkownika.

JavaScript w projekcie:
- komunikuje się z backendem przez Fetch API (AJAX),
- aktualizuje widoki w czasie rzeczywistym (polling),
- obsługuje akcje użytkownika (kliknięcia, formularze),
- synchronizuje stan gry między uczestnikami.
- `session.js` – obsługa sesji gry (polling, participants, coin flip, heartbeat)
- `main.js` – ogólne skrypty wspólne dla aplikacji (jeśli występuje)

### Przykład użycia
```javascript
fetch('/api/session/participants?session_id=' + sessionId)
  .then(res => res.json())
  .then(data => renderParticipants(data.participants));
```
<br>

---

# Fetch API / AJAX

Aplikacja wykorzystuje **Fetch API** do asynchronicznej komunikacji
pomiędzy frontendem (JavaScript) a backendem (PHP API).
Pozwala to na aktualizację danych **bez przeładowania strony**.

Fetch API jest używane m.in. do:
- pobierania listy uczestników sesji,
- wykonywania akcji gry (coin flip),
- synchronizacji stanu gry (polling),
- obsługi presence (heartbeat, leave).

### Przykładowy plik
`public/scripts/session.js`

### Przykład użycia
```javascript
fetch('/api/session/participants?session_id=' + sessionId)
  .then(res => res.json())
  .then(data => renderParticipants(data.participants));
```

<br>

---

# Responsywność

Aplikacja jest zaprojektowana w sposób responsywny, tak aby poprawnie
działała zarówno na komputerach, jak i na urządzeniach mobilnych.

Responsywność realizowana jest poprzez:
- elastyczne układy (flexbox),
- skalowalne komponenty interfejsu,
- dostosowanie widoków do szerokości ekranu.

### Przykładowy plik
`public/styles/app.css`

### Przykład użycia
```css
.container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 16px;
}

@media (max-width: 768px) {
    .container {
        padding: 12px;
    }
}
```

<br>

---
# Logowanie

Logowanie umożliwia użytkownikowi dostęp do funkcji aplikacji wymagających autoryzacji.
Po poprawnym zalogowaniu identyfikator użytkownika jest zapisywany w sesji PHP (`$_SESSION['user_id']`).

### Przykładowy plik
`src/controllers/SecurityController.php`

### Przykład użycia
```php
$user = $this->userRepository->getUserByEmail($email);

if (!$user || !password_verify($password, $user['password'])) {
    $this->render('login', ['messages' => 'Invalid email or password']);
    return;
}

$_SESSION['user_id'] = (int)$user['id'];
session_regenerate_id(true);

$this->redirect('/tools');
```

<br>

---

# Sesja użytkownika

Sesja użytkownika służy do przechowywania informacji o zalogowanym użytkowniku
oraz kontroli dostępu do chronionych zasobów aplikacji.
Sesja oparta jest o mechanizm PHP `$_SESSION`.

### Przykładowy plik
`src/controllers/AppController.php`

### Przykład użycia
```php
protected function requireAuth(): void
{
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        $this->redirect('/login');
    }
}
```

<br>

---

# Uprawnienia użytkowników

Aplikacja rozróżnia **dwa typy uprawnień w ramach sesji gry**:
- **Host (twórca sesji)** – posiada kontrolę nad przebiegiem gry,
- **Uczestnik** – może dołączyć do sesji i obserwować rozgrywkę.

Uprawnienia nie są realizowane przez osobne role w bazie danych,
lecz wynikają z relacji użytkownika z daną sesją gry.

### Przykładowy plik
`public/scripts/session.js`

### Przykład użycia
```javascript
const isHost = (root.dataset.isHost || '0') === '1';

if (!isHost) {
    actionBtn.disabled = true;
    hint.textContent = 'Only the host can control the game.';
}
```

<br>

---

# Wylogowanie

Wylogowanie usuwa aktualną sesję użytkownika i uniemożliwia dalszy dostęp
do zasobów wymagających autoryzacji.

### Przykładowy plik
`src/controllers/SecurityController.php`

### Przykład użycia
```php
public function logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    $this->redirect('/login');
}
```

<br>

---

# Transakcje

Transakcje są używane do zapewnienia spójności logiki gry
oraz ochrony przed race condition.

### Przykład
`GameSessionRepository.php`

```php
$db->beginTransaction();

try {
    // eliminacja graczy i wyłonienie zwycięzcy
    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
}
```

<br>

---

# Akcje na referencjach (Foreign Key Actions)

Akcje te są stosowane głównie dla danych technicznych,
które nie mają sensu bez rekordu nadrzędnego.

### Przykład
Relacja uczestników sesji do sesji gry:

`docker/db/init.sql`

```sql
game_session_participants.session_id
→ game_sessions.id
ON DELETE CASCADE
```

<br>

---

# Bezpieczeństwo (bingo)

## Ochrona przed SQL injection (prepared statements) - A1

### Przykład

`src/repository/UserRepository.php`

```php
$stmt = $this->database->connect()->prepare('
            SELECT *
            FROM users
            WHERE email = :email
            LIMIT 1
        ');
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
```

## Nie zdradzamy, czy email istnieje - B1

### Przykład

`src/controllers/SecurityController.php`

```php
if (!$user || !password_verify($password, $user['password'])) {
            $this->render('login', [
                'messages' => 'Invalid email or password',
                'old' => $old
            ]);
            return;
        }
```

## Walidacja formatu email po stronie serwera - C1

### Przykład

`src/controllers/SecurityController.php`

```php
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->render('register', [
                'messages' => 'Invalid email format',
                'old' => $old,
                'pwRules' => $pwRules
            ]);
            return;
        }
```

## User Repository zarządzany jako singleton - D1

### Przykład

`src/repository/UserRepository.php`

```php
public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }
```

## Login i register przyjmują dane tylko na POST - A2

### Przykład

`src/controllers/SecurityController.php`

```php
public function login(): void
    {
        if ($this->isGet()) {
            
            if (isset($_SESSION['user_id'])) {
                $this->redirect($this->consumeSafeRedirectAfterLogin());
                return;
            }
```

## Hasła przechowywane jako hash (bcrypt) - E2

### Przykład

`src/repository/UserRepository.php`

```php
$hash = password_hash($password, PASSWORD_BCRYPT);
```

## Hasła nie mogą pojawiać się w logach - A3

### Przykład

`Trzeba uwierzyć na słowo, bo ciężko pokazać przykład kodu który nie istnieje`

## Regeneracja ID sesji po logowaniu - B3

### Przykład

`src/controllers/SecurityController.php`

```php
if (function_exists('session_regenerate_id')) {
    session_regenerate_id(true);
}
```

## Walidacja złożoności hasła - B4

### Przykład

`src/controllers/SecurityController.php`

```php
if (!$this->isPasswordStrong($password)) {
            $this->render('register', [
                'messages' => 'Password does not meet requirements',
                'old' => $old,
                'pwRules' => $pwRules
            ]);
            return;

private function isPasswordStrong(string $password): bool
    {
        $lengthOk = strlen($password) >= 9;
        $digitOk = preg_match('/\d/', $password) === 1;
        $upperOk = preg_match('/[A-Z]/', $password) === 1;
        $specialOk = preg_match('/[^a-zA-Z0-9]/', $password) === 1;

        return $lengthOk && $digitOk && $upperOk && $specialOk;
    }
```

## Escaping w widokach (XSS) - D4

### Przykład

`src/public/views/session.html`

```php
<?= htmlspecialchars($session['game_type']) ?>
```

## Poprawne kody HTTP - A5

### Przykład

`src/controllers/GameApiController.php`

```php
if ($sessionId === '') {
    $this->json(['error' => 'session_id is required'], 400);
}
```

## Nie przekazujemy hasła do widoku - B5

### Przykład

`Trzeba uwierzyć na słowo, bo ciężko pokazać przykład kodu który nie istnieje`

## Pobieramy tylko potrzebne dane - C5

### Przykład

`src/repository/UserRepository.php`

```php
$stmt = $this->database->connect()->prepare('
            SELECT id, nickname, email, avatar_url, created_at
            FROM users
            WHERE id = :id
            LIMIT 1
        ');
```

## Poprawne wylogowanie - niszczenie sesji - D5

### Przykład

`src/controllers/SecurityController.php`

```php
$_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if (isset($_SESSION['user_id'])) {
            $repo = new GameSessionRepository();
            $repo->leaveAllSessions((int)$_SESSION['user_id']);
        }

        session_destroy();
        $this->redirect('/login');
```

## Podsumowanie Bingo

<img width="729" height="713" alt="image" src="https://github.com/user-attachments/assets/7f1a60fb-f3a0-4314-88a8-340b1c190460" />


