<?php
declare(strict_types=1);

namespace Prismatch\Core;

use PDO;

class SchemaManager {
    private static bool $initialized = false;

    public static function initSchema(PDO $pdo): void {
        if (self::$initialized) {
            return;
        }

        // Users
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id CHAR(36) NOT NULL,
                email VARCHAR(320) NOT NULL,
                created_at DATETIME(3) NOT NULL,
                last_login DATETIME(3) NOT NULL,
                total_plays INT NOT NULL DEFAULT 0,
                total_wins INT NOT NULL DEFAULT 0,
                best_level INT NOT NULL DEFAULT 0,
                total_correct INT NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY uq_users_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Games (Single Player)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS games (
                id CHAR(36) NOT NULL,
                user_id CHAR(36) NOT NULL,
                email VARCHAR(320) NOT NULL,
                created_at DATETIME(3) NOT NULL,
                finished_at DATETIME(3) NOT NULL,
                duration_ms INT NOT NULL,
                reached_level INT NOT NULL,
                total_correct INT NOT NULL,
                score INT NOT NULL DEFAULT 0,
                won TINYINT(1) NOT NULL,
                language VARCHAR(16) NULL,
                country VARCHAR(8) NULL,
                PRIMARY KEY (id),
                INDEX idx_games_user_created (user_id, created_at),
                INDEX idx_games_email_created (email, created_at),
                CONSTRAINT fk_games_user FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Rounds (Single Player)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS rounds (
                id CHAR(36) NOT NULL,
                game_id CHAR(36) NOT NULL,
                level INT NOT NULL,
                target_color VARCHAR(16) NOT NULL,
                grid_colors_json LONGTEXT NOT NULL,
                picked_color VARCHAR(16) NULL,
                response_ms INT NOT NULL,
                is_correct TINYINT(1) NOT NULL,
                created_at DATETIME(3) NOT NULL,
                PRIMARY KEY (id),
                INDEX idx_rounds_game_level (game_id, level),
                CONSTRAINT fk_rounds_game FOREIGN KEY (game_id) REFERENCES games(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Daily Challenge Tables
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS daily_scores (
                id CHAR(36) NOT NULL,
                day_utc DATE NOT NULL,
                user_id CHAR(36) NULL,
                user_email VARCHAR(320) NULL,
                anon_id CHAR(36) NULL,
                reached_level INT NOT NULL,
                correct_count INT NOT NULL,
                score INT NOT NULL DEFAULT 0,
                duration_ms INT NOT NULL,
                won TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME(3) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_daily_user (day_utc, user_id),
                UNIQUE KEY uq_daily_anon (day_utc, anon_id),
                KEY idx_day (day_utc),
                KEY idx_score (score, reached_level, correct_count, duration_ms)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS daily_rounds (
                id CHAR(36) NOT NULL,
                daily_score_id CHAR(36) NOT NULL,
                stage INT NOT NULL,
                target_color VARCHAR(16) NOT NULL,
                picked_color VARCHAR(16) NOT NULL,
                response_ms INT NOT NULL,
                is_correct TINYINT(1) NOT NULL,
                grid_json LONGTEXT NULL,
                created_at DATETIME(3) NOT NULL,
                PRIMARY KEY (id),
                KEY idx_score_stage (daily_score_id, stage),
                CONSTRAINT fk_daily_rounds_score FOREIGN KEY (daily_score_id)
                    REFERENCES daily_scores(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Multiplayer Room Tables
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS rooms (
                id CHAR(36) NOT NULL,
                guid CHAR(36) NOT NULL,
                name VARCHAR(80) NULL,
                owner_id CHAR(36) NOT NULL,
                owner_email VARCHAR(320) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'waiting',
                rounds_total INT NOT NULL,
                current_round INT NOT NULL DEFAULT 0,
                finished_round INT NULL,
                max_players INT NOT NULL DEFAULT 25,
                created_at DATETIME(3) NOT NULL,
                started_at DATETIME(3) NULL,
                finished_at DATETIME(3) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_rooms_guid (guid),
                KEY idx_rooms_owner (owner_id),
                KEY idx_rooms_owner_email (owner_email),
                KEY idx_rooms_status (status),
                CONSTRAINT fk_rooms_owner FOREIGN KEY (owner_id) REFERENCES users(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS room_players (
                id CHAR(36) NOT NULL,
                room_id CHAR(36) NOT NULL,
                user_id CHAR(36) NOT NULL,
                email VARCHAR(320) NOT NULL,
                joined_at DATETIME(3) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'active',
                eliminated_round INT NULL,
                score INT NOT NULL DEFAULT 0,
                correct INT NOT NULL DEFAULT 0,
                last_active DATETIME(3) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_room_player (room_id, user_id),
                KEY idx_room_players_room (room_id),
                KEY idx_room_players_user (user_id),
                KEY idx_room_players_email (email),
                CONSTRAINT fk_room_players_room FOREIGN KEY (room_id) REFERENCES rooms(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_room_players_user FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS room_rounds (
                id CHAR(36) NOT NULL,
                room_id CHAR(36) NOT NULL,
                round_index INT NOT NULL,
                question_json LONGTEXT NOT NULL,
                started_at DATETIME(3) NOT NULL,
                ended_at DATETIME(3) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_room_round (room_id, round_index),
                KEY idx_room_rounds_room (room_id),
                CONSTRAINT fk_room_rounds_room FOREIGN KEY (room_id) REFERENCES rooms(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS room_events (
                id CHAR(36) NOT NULL,
                room_id CHAR(36) NOT NULL,
                round_index INT NOT NULL,
                user_id CHAR(36) NULL,
                email VARCHAR(320) NOT NULL,
                event_type VARCHAR(32) NOT NULL,
                payload_json LONGTEXT NOT NULL,
                created_at DATETIME(3) NOT NULL,
                PRIMARY KEY (id),
                KEY idx_room_events_room (room_id),
                KEY idx_room_events_type (event_type),
                KEY idx_room_events_user (user_id),
                CONSTRAINT fk_room_events_room FOREIGN KEY (room_id) REFERENCES rooms(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_room_events_user FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        self::$initialized = true;
    }
}
