-- ---------------------------------------------------------------------------
-- Ancestral Journey progress
--
-- Optional, additive feature table. The application tolerates its absence
-- (the database user may not have DDL rights), so this migration can be run by
-- an administrator at any time. It does not modify existing tables.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `ancestral_journeys` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      BIGINT UNSIGNED NOT NULL,
  `family_id`    BIGINT UNSIGNED NOT NULL,
  `step`         VARCHAR(40)     NOT NULL DEFAULT 'intro',
  `started_at`   DATETIME        NULL,
  `last_seen_at` DATETIME        NULL,
  `completed_at` DATETIME        NULL,
  `meta`         TEXT            NULL,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ancestral_journeys_user_family` (`user_id`, `family_id`),
  KEY `idx_ancestral_journeys_user` (`user_id`, `completed_at`),
  KEY `idx_ancestral_journeys_family` (`family_id`),
  CONSTRAINT `fk_ancestral_journeys_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ancestral_journeys_family` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
