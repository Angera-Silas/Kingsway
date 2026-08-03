-- ============================================================
-- Migration 001: Create Sports Module Tables
-- Sports-specific tables for teams, fixtures, player stats, standings
-- Follows the Activities module architecture: per-category managers
-- under the shared ActivitiesController umbrella
-- ============================================================

CREATE TABLE IF NOT EXISTS sports_teams (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    sport_type      VARCHAR(50) NOT NULL COMMENT 'football, basketball, volleyball, athletics, swimming, tennis, rugby, cricket, netball, hockey, other',
    category_id     INT NOT NULL COMMENT 'FK to activity_categories (references the Sports category)',
    coach_id        INT DEFAULT NULL COMMENT 'FK to staff — team coach/patron',
    captain_id      INT DEFAULT NULL COMMENT 'FK to students — team captain',
    description     TEXT DEFAULT NULL,
    season          VARCHAR(50) DEFAULT NULL COMMENT 'e.g. "2026 Term 1", "2025/2026"',
    wins            INT DEFAULT 0,
    losses          INT DEFAULT 0,
    draws           INT DEFAULT 0,
    status          ENUM('active','inactive','disbanded') DEFAULT 'active',
    created_by      INT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES activity_categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (coach_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (captain_id) REFERENCES students(id) ON DELETE SET NULL,
    INDEX idx_sport_type (sport_type),
    INDEX idx_season (season),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sports_team_members (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    team_id         INT NOT NULL,
    student_id      INT NOT NULL,
    position        VARCHAR(100) DEFAULT NULL COMMENT 'e.g. Forward, Goalkeeper, Point Guard',
    jersey_number   INT DEFAULT NULL,
    joined_date     DATE DEFAULT NULL,
    status          ENUM('active','inactive','graduated','dropped') DEFAULT 'active',
    FOREIGN KEY (team_id) REFERENCES sports_teams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    INDEX idx_team (team_id),
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sports_fixtures (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    team_id         INT NOT NULL,
    opponent        VARCHAR(200) NOT NULL,
    fixture_type    ENUM('friendly','league','cup','tournament','training') DEFAULT 'friendly',
    venue           VARCHAR(255) DEFAULT NULL,
    fixture_date    DATETIME NOT NULL,
    home_away       ENUM('home','away','neutral') DEFAULT 'home',
    status          ENUM('scheduled','in_progress','completed','cancelled','postponed') DEFAULT 'scheduled',
    our_score       INT DEFAULT NULL,
    opponent_score  INT DEFAULT NULL,
    result          ENUM('win','loss','draw','') DEFAULT NULL COMMENT 'Populated when status=completed',
    match_report    TEXT DEFAULT NULL,
    referee         VARCHAR(150) DEFAULT NULL,
    season          VARCHAR(50) DEFAULT NULL,
    created_by      INT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES sports_teams(id) ON DELETE CASCADE,
    INDEX idx_fixture_date (fixture_date),
    INDEX idx_status (status),
    INDEX idx_season (season),
    INDEX idx_team_date (team_id, fixture_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sports_player_stats (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    fixture_id      INT NOT NULL,
    player_id       INT NOT NULL COMMENT 'FK to students',
    team_id         INT NOT NULL,
    goals_scored    INT DEFAULT 0,
    assists         INT DEFAULT 0,
    minutes_played  INT DEFAULT 0,
    yellow_cards    INT DEFAULT 0,
    red_cards       INT DEFAULT 0,
    subs_in         TINYINT DEFAULT 0,
    subs_out        TINYINT DEFAULT 0,
    rating          DECIMAL(3,1) DEFAULT NULL COMMENT '1.0-10.0 performance rating',
    notes           TEXT DEFAULT NULL,
    FOREIGN KEY (fixture_id) REFERENCES sports_fixtures(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES students(id) ON DELETE RESTRICT,
    FOREIGN KEY (team_id) REFERENCES sports_teams(id) ON DELETE CASCADE,
    INDEX idx_fixture (fixture_id),
    INDEX idx_player (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sports_standings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    competition     VARCHAR(200) DEFAULT NULL COMMENT 'e.g. Inter-House Football, Regional Championships',
    team_id         INT NOT NULL,
    played          INT DEFAULT 0,
    won             INT DEFAULT 0,
    lost            INT DEFAULT 0,
    drawn           INT DEFAULT 0,
    goals_for       INT DEFAULT 0,
    goals_against   INT DEFAULT 0,
    goal_difference INT GENERATED ALWAYS AS (goals_for - goals_against) STORED,
    points          INT DEFAULT 0,
    position        INT DEFAULT NULL,
    season          VARCHAR(50) DEFAULT NULL,
    FOREIGN KEY (team_id) REFERENCES sports_teams(id) ON DELETE CASCADE,
    INDEX idx_competition (competition),
    INDEX idx_season (season),
    INDEX idx_points (points DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
