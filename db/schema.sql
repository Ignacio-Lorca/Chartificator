CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  display_name VARCHAR(64) NOT NULL,
  email VARCHAR(255) NULL,
  password_hash VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS songs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  bpm DECIMAL(6,2) NOT NULL,
  time_signature_num TINYINT UNSIGNED NOT NULL,
  time_signature_den TINYINT UNSIGNED NOT NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by_user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  song_id BIGINT UNSIGNED NULL,
  active_song_id BIGINT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  invite_code VARCHAR(32) NOT NULL UNIQUE,
  current_bpm DECIMAL(6,2) NOT NULL,
  beats_per_bar TINYINT UNSIGNED NOT NULL,
  play_state ENUM('playing','paused') NOT NULL DEFAULT 'paused',
  bar_offset DECIMAL(10,4) NOT NULL DEFAULT 1,
  started_at_ms BIGINT NOT NULL DEFAULT 0,
  revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (song_id) REFERENCES songs(id),
  FOREIGN KEY (active_song_id) REFERENCES songs(id)
);

CREATE TABLE IF NOT EXISTS session_songs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  song_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_session_song (session_id, song_id),
  KEY idx_session_songs_order (session_id, sort_order),
  FOREIGN KEY (session_id) REFERENCES sessions(id),
  FOREIGN KEY (song_id) REFERENCES songs(id)
);

CREATE TABLE IF NOT EXISTS session_memberships (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  display_name VARCHAR(64) NOT NULL,
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_session_user (session_id, user_id),
  FOREIGN KEY (session_id) REFERENCES sessions(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS session_presence (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  display_name VARCHAR(64) NOT NULL,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_session_presence_user (session_id, user_id),
  KEY idx_session_presence_last_seen (session_id, last_seen_at),
  FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS song_sections (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  song_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(40) NOT NULL,
  color_hex VARCHAR(7) NOT NULL DEFAULT '#2B7CFF',
  shared_text TEXT NOT NULL,
  bar_start INT UNSIGNED NOT NULL,
  bar_end INT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (song_id) REFERENCES songs(id)
);

CREATE TABLE IF NOT EXISTS song_section_private_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section_id BIGINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  note_text TEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_section_owner (section_id, owner_user_id),
  KEY idx_owner_section (owner_user_id, section_id),
  FOREIGN KEY (section_id) REFERENCES song_sections(id) ON DELETE CASCADE,
  FOREIGN KEY (owner_user_id) REFERENCES users(id)
);

-- Migration note for existing deployments:
-- ALTER TABLE song_sections ADD COLUMN color_hex VARCHAR(7) NOT NULL DEFAULT '#2B7CFF' AFTER label;
-- ALTER TABLE song_sections ADD COLUMN shared_text TEXT NOT NULL AFTER color_hex;
-- CREATE TABLE song_section_private_notes (...);
-- ALTER TABLE sessions ADD COLUMN active_song_id BIGINT UNSIGNED NULL AFTER song_id;
-- CREATE TABLE session_songs (...);
-- CREATE TABLE session_presence (...);
-- INSERT INTO session_songs (session_id, song_id, sort_order)
-- SELECT id, COALESCE(active_song_id, song_id), 0 FROM sessions;
-- UPDATE sessions SET active_song_id = COALESCE(active_song_id, song_id);

CREATE TABLE IF NOT EXISTS bar_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  song_id BIGINT UNSIGNED NOT NULL,
  bar_number INT UNSIGNED NOT NULL,
  layer_type ENUM('shared','private') NOT NULL,
  owner_user_id BIGINT UNSIGNED NULL,
  note_text TEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (song_id) REFERENCES songs(id),
  FOREIGN KEY (owner_user_id) REFERENCES users(id),
  INDEX idx_song_bar (song_id, bar_number),
  INDEX idx_song_layer (song_id, layer_type),
  INDEX idx_song_owner_bar (song_id, owner_user_id, bar_number),
  UNIQUE KEY uq_note (song_id, bar_number, layer_type, owner_user_id)
);
