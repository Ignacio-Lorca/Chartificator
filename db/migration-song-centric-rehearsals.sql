SET @database_name = DATABASE();

SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @database_name
        AND TABLE_NAME = 'song_sections'
        AND COLUMN_NAME = 'color_hex'
    ),
    'SELECT 1',
    'ALTER TABLE song_sections ADD COLUMN color_hex VARCHAR(7) NOT NULL DEFAULT ''#2B7CFF'' AFTER label'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @database_name
        AND TABLE_NAME = 'sessions'
        AND COLUMN_NAME = 'song_id'
        AND IS_NULLABLE = 'YES'
    ),
    'SELECT 1',
    'ALTER TABLE sessions MODIFY COLUMN song_id BIGINT UNSIGNED NULL'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @database_name
        AND TABLE_NAME = 'sessions'
        AND COLUMN_NAME = 'active_song_id'
    ),
    'SELECT 1',
    'ALTER TABLE sessions ADD COLUMN active_song_id BIGINT UNSIGNED NULL AFTER song_id'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS session_songs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  song_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_session_song (session_id, song_id),
  KEY idx_session_songs_order (session_id, sort_order),
  CONSTRAINT fk_session_songs_session FOREIGN KEY (session_id) REFERENCES sessions(id),
  CONSTRAINT fk_session_songs_song FOREIGN KEY (song_id) REFERENCES songs(id)
);

INSERT INTO session_songs (session_id, song_id, sort_order)
SELECT s.id, COALESCE(s.active_song_id, s.song_id), 0
FROM sessions s
LEFT JOIN session_songs ss
  ON ss.session_id = s.id
  AND ss.song_id = COALESCE(s.active_song_id, s.song_id)
WHERE ss.id IS NULL;

UPDATE sessions
SET active_song_id = COALESCE(active_song_id, song_id)
WHERE active_song_id IS NULL;
