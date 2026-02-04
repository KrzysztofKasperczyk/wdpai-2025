------------------------------------------------------------
-- USERS: podstawowe konto użytkownika aplikacji
------------------------------------------------------------
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    nickname VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,           -- hash hasła (bcrypt)
    avatar_url VARCHAR(255),                  -- link do zdjęcia użytkownika
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

------------------------------------------------------------
-- USER_STATS: statystyki użytkownika
------------------------------------------------------------
CREATE TABLE user_stats (
    id SERIAL PRIMARY KEY,
    user_id INTEGER UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    total_draws INTEGER DEFAULT 0,            -- ile losowań wykonał
    wins INTEGER DEFAULT 0,                   -- wygrane
    losses INTEGER DEFAULT 0,                 -- przegrane
    draws INTEGER DEFAULT 0,                  -- remisy (opcjonalnie)
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

------------------------------------------------------------
-- USER_FRIENDS: relacja znajomych (user <-> user)
------------------------------------------------------------
CREATE TABLE user_friends (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    friend_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status VARCHAR(20) NOT NULL DEFAULT 'accepted'
        CHECK (status IN ('pending', 'accepted', 'blocked')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, friend_id)
);

------------------------------------------------------------
-- DISPUTES: spór między użytkownikami (sesja rozstrzygania)
------------------------------------------------------------
CREATE TABLE disputes (
    id SERIAL PRIMARY KEY,
    title VARCHAR(200) NOT NULL,              -- np. "Kto wybiera film?"
    description TEXT,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open'
        CHECK (status IN ('open', 'resolved', 'cancelled')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP
);

------------------------------------------------------------
-- DISPUTE_PARTICIPANTS: uczestnicy sporu
------------------------------------------------------------
CREATE TABLE dispute_participants (
    id SERIAL PRIMARY KEY,
    dispute_id INTEGER NOT NULL REFERENCES disputes(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role VARCHAR(20) DEFAULT 'participant'
        CHECK (role IN ('creator', 'participant')),
    UNIQUE (dispute_id, user_id)
);

------------------------------------------------------------
-- DRAWS: konkretne losowanie wykonane w ramach sporu
------------------------------------------------------------
CREATE TABLE draws (
    id SERIAL PRIMARY KEY,
    dispute_id INTEGER NOT NULL REFERENCES disputes(id) ON DELETE CASCADE,
    draw_type VARCHAR(30) NOT NULL 
        CHECK (draw_type IN ('coin_flip', 'dice_roll', 'random_number', 'custom')),
    params JSONB,                              -- dodatkowe parametry (np. ilość ścian kostki)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

------------------------------------------------------------
-- DRAW_RESULTS: wynik losowania dla uczestników
------------------------------------------------------------
CREATE TABLE draw_results (
    id SERIAL PRIMARY KEY,
    draw_id INTEGER NOT NULL REFERENCES draws(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    result_value VARCHAR(50),                  -- np. "heads", "tails", "4"
    is_winner BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (draw_id, user_id)
);

------------------------------------------------------------
-- SEED DANYCH
------------------------------------------------------------

-- Przykladowi użytkownicy
INSERT INTO users (nickname, email, password, avatar_url) VALUES
('RandomKing', 'random.king@example.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'https://randomuser.me/api/portraits/men/1.jpg'),

('CoinMaster', 'coin.master@example.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'https://randomuser.me/api/portraits/men/2.jpg'),

('DiceQueen', 'dice.queen@example.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'https://randomuser.me/api/portraits/women/1.jpg');

------------------------------------------------------------
-- Statystyki użytkowników
------------------------------------------------------------
INSERT INTO user_stats (user_id, total_draws, wins, losses, draws) VALUES
(1, 10, 6, 3, 1),
(2, 7, 3, 4, 0),
(3, 15, 9, 5, 1);

------------------------------------------------------------
-- Znajomi
------------------------------------------------------------
INSERT INTO user_friends (user_id, friend_id, status) VALUES
(1, 2, 'accepted'),
(1, 3, 'accepted'),
(2, 3, 'accepted');

------------------------------------------------------------
-- Przykładowy spór
------------------------------------------------------------
INSERT INTO disputes (title, description, created_by, status) VALUES
('Kto wybiera film?',
 'Nie możemy się dogadać co oglądać dzisiaj wieczorem.',
 1,
 'open');

------------------------------------------------------------
-- Uczestnicy sporu
------------------------------------------------------------
INSERT INTO dispute_participants (dispute_id, user_id, role) VALUES
(1, 1, 'creator'),
(1, 2, 'participant');

------------------------------------------------------------
-- Losowanie: rzut monetą
------------------------------------------------------------
INSERT INTO draws (dispute_id, draw_type, params) VALUES
(1, 'coin_flip', '{"sides": ["heads", "tails"]}');

------------------------------------------------------------
-- Wyniki losowania
------------------------------------------------------------
INSERT INTO draw_results (draw_id, user_id, result_value, is_winner) VALUES
(1, 1, 'heads', TRUE),
(1, 2, 'tails', FALSE);

------------------------------------------------------------
-- Aktualizacja statystyk po losowaniu (normalnie robi to backend)
------------------------------------------------------------
UPDATE user_stats
SET total_draws = total_draws + 1,
    wins = wins + 1,
    last_activity = CURRENT_TIMESTAMP
WHERE user_id = 1;

UPDATE user_stats
SET total_draws = total_draws + 1,
    losses = losses + 1,
    last_activity = CURRENT_TIMESTAMP
WHERE user_id = 2;


------------------------------------------------------------
-- GAME_SESSIONS: wspólna sesja gry (invite link)
------------------------------------------------------------
CREATE TABLE IF NOT EXISTS game_sessions (
    id UUID PRIMARY KEY,
    game_type VARCHAR(30) NOT NULL
        CHECK (game_type IN ('coin_flip', 'roll_dice', 'spin_wheel')),
    created_by INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status VARCHAR(20) DEFAULT 'active'
        CHECK (status IN ('active', 'finished')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

------------------------------------------------------------
-- GAME_SESSION_PARTICIPANTS: uczestnicy sesji
------------------------------------------------------------
CREATE TABLE IF NOT EXISTS game_session_participants (
    id SERIAL PRIMARY KEY,
    session_id UUID NOT NULL REFERENCES game_sessions(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (session_id, user_id)
);

------------------------------------------------------------
-- GAME_SESSION_EVENTS: zdarzenia (wyniki losowań)
------------------------------------------------------------
CREATE TABLE IF NOT EXISTS game_session_events (
    id SERIAL PRIMARY KEY,
    session_id UUID NOT NULL REFERENCES game_sessions(id) ON DELETE CASCADE,
    event_type VARCHAR(30) NOT NULL,
    payload JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
