<?php
require_once __DIR__.'/config.php';

function pdo(): PDO {
  static $pdo=null; if ($pdo) return $pdo;
  $pdo = new PDO("mysql:host=".DB_HOST.";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $pdo->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;");
  $pdo->exec("USE `".DB_NAME."`;");

  $pdo->exec("CREATE TABLE IF NOT EXISTS users(
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $pdo->exec("CREATE TABLE IF NOT EXISTS matches(
    id INT AUTO_INCREMENT PRIMARY KEY,
    stage VARCHAR(60) NOT NULL,
    grp VARCHAR(32) NULL,
    md TINYINT NULL,
    kickoff DATETIME NOT NULL,
    home_team VARCHAR(100) NOT NULL,
    away_team VARCHAR(100) NOT NULL,
    home_score TINYINT NULL,
    away_score TINYINT NULL,
    is_finished TINYINT(1) NOT NULL DEFAULT 0,
    api_source VARCHAR(50) NULL,
    api_match_id VARCHAR(32) NULL,
    api_season INT NULL,
    api_status VARCHAR(20) NULL,
    last_sync DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_api (api_source, api_match_id),
    KEY idx_kickoff (kickoff),
    KEY idx_md (md)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // score OU 1X2
  $pdo->exec("CREATE TABLE IF NOT EXISTS predictions(
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    match_id INT NOT NULL,
    pred_home TINYINT NULL,
    pred_away TINYINT NULL,
    pick ENUM('H','D','A') NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_match (user_id, match_id),
    CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // chat
  $pdo->exec("CREATE TABLE IF NOT EXISTS chat_sessions(
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    php_session_id VARCHAR(64) NOT NULL,
    model VARCHAR(100) NULL,
    title VARCHAR(200) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_active DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_php (php_session_id),
    CONSTRAINT fk_chat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages(
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    role ENUM('system','user','assistant','tool') NOT NULL,
    content MEDIUMTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sess (session_id),
    CONSTRAINT fk_chat_session FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  return $pdo;
}
